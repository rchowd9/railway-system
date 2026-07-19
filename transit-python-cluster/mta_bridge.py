import time
import json
import redis
import random
from datetime import datetime, timedelta

try:
    r = redis.Redis(host='127.0.0.1', port=6379, decode_responses=True, protocol=2)
    r.ping()
    print("✅ Redis connection verified successfully.")
except Exception as e:
    print(f"❌ Could not connect to Redis: {e}")
    exit(1)

print("📡 Python 3.14 MTA Core Ingestion Engine Active with Robust Defensive Architecture...")

last_sim_fetch = 0
sim_fetch_interval = 30  
cached_baseline_stream = []

def generate_local_mta_stream():
    routes = ["1", "A", "Q", "R"]
    origins = ["South Ferry", "Utica Ave", "Flatbush Ave", "Coney Island", "Astoria"]
    destinations = ["Van Cortlandt Park", "Inwood - 207 St", "Astoria - Ditmars Blvd", "Stillwell Ave"]
    
    simulated_timetable = []
    now = datetime.now()
    current_offset_minutes = 2 
    
    for i in range(8):
        # Enforce varied increments so countdown values never overlap identically
        current_offset_minutes += random.randint(6, 14)
        random_seconds = random.randint(0, 59)

        arrival_dt = now + timedelta(minutes=current_offset_minutes, seconds=random_seconds)
        departure_dt = arrival_dt + timedelta(minutes=8)
        
        simulated_timetable.append({
            'id': f"{random.randint(100000, 999999)}",
            'line': f"{random.choice(routes)} Line Commuter",
            'origin': random.choice(origins),
            'destination': random.choice(destinations),
            'arrival_timestamp': int(arrival_dt.timestamp()),
            'departure_timestamp': int(departure_dt.timestamp()),
            # %I (12-hour clock) removes broken 24-hour PM collisions like 20:02:46 PM
            'arrival': arrival_dt.strftime("%I:%M:%S %p"),
            'departure': departure_dt.strftime("%I:%M:%S %p"),
            'lat': random.uniform(40.70, 40.85),
            'lon': random.uniform(-74.00, -73.90)
        })
    return simulated_timetable

while True:
    current_time = time.time()
    
    if current_time - last_sim_fetch >= sim_fetch_interval or not cached_baseline_stream:
        cached_baseline_stream = generate_local_mta_stream()
        last_sim_fetch = current_time
        print("🔄 Baseline Cache Refreshed: New unique simulation timelines mapped.")
    
    live_timetable = list(cached_baseline_stream)
    
    try:
        admin_override = r.get('mta-admin-override')
        if admin_override:
            try:
                overridden_trains = json.loads(admin_override)
                
                # EDGE CASE: If data is corrupted into a non-list format, normalize safely
                if not isinstance(overridden_trains, list):
                    overridden_trains = []
                    
                updated_overrides = []
                now_ts = int(time.time())
                
                for train in overridden_trains:
                    # EDGE CASE: Skip malformed entry blocks that aren't dict profiles
                    if not isinstance(train, dict) or 'id' not in train:
                        continue
                        
                    if 'arrival_timestamp' not in train or not train['arrival_timestamp']:
                        train['arrival_timestamp'] = now_ts + 600
                        train['departure_timestamp'] = train['arrival_timestamp'] + 480
                    
                    try:
                        arrival_dt = datetime.fromtimestamp(int(train['arrival_timestamp']))
                        train['arrival_timestamp'] = int(arrival_dt.timestamp())
                        train['arrival'] = arrival_dt.strftime("%I:%M:%S %p")
                    except (ValueError, TypeError):
                        train['arrival_timestamp'] = now_ts + 600
                        train['arrival'] = datetime.fromtimestamp(train['arrival_timestamp']).strftime("%I:%M:%S %p")
                    
                    if 'departure_timestamp' not in train or not train['departure_timestamp']:
                        train['departure_timestamp'] = train['arrival_timestamp'] + 480
                        train['departure'] = datetime.fromtimestamp(train['departure_timestamp']).strftime("%I:%M:%S %p")
                    
                    if int(train['arrival_timestamp']) > (now_ts - 60):
                        updated_overrides.append(train)
                
                r.set('mta-admin-override', json.dumps(updated_overrides))
                live_timetable = updated_overrides + live_timetable
                
            except json.JSONDecodeError:
                print("⚠️ Malformed override payload detected. Purging corrupted Redis vector.")
                r.delete('mta-admin-override') # Fixed: Using valid r.delete() instead of r.del()
    except Exception as redis_err:
        print(f"⚠️ Redis connection glitch encountered in main processing thread: {redis_err}")

    try:
        # Compute server-side ETA for each train to reduce client-side skew.
        now_ts = int(time.time())
        enriched = []
        for t in live_timetable[:10]:
            # Ensure arrival_timestamp exists and is an int
            at = t.get('arrival_timestamp')
            if not at:
                t['arrival_timestamp'] = now_ts + 600
                t['departure_timestamp'] = t['arrival_timestamp'] + 480
                at = t['arrival_timestamp']

            try:
                at = int(at)
            except Exception:
                at = now_ts + 600
                t['arrival_timestamp'] = at
                t['departure_timestamp'] = at + 480

            # ETA in seconds relative to server now
            t['eta_seconds'] = at - now_ts

            if 'departure_timestamp' not in t or not t['departure_timestamp']:
                t['departure_timestamp'] = at + 480

            # Ensure a human-friendly arrival string is present
            try:
                t['arrival'] = datetime.fromtimestamp(at).strftime("%I:%M:%S %p")
            except Exception:
                t['arrival'] = datetime.fromtimestamp(now_ts + t['eta_seconds']).strftime("%I:%M:%S %p")

            enriched.append(t)

        r.set('mta-live-schedule', json.dumps(enriched))
    except Exception as write_err:
        print(f"⚠️ Unable to sync live schedule matrix: {write_err}")
        
    time.sleep(1)