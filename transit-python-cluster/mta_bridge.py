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
    # Structural Map Layout: Maps active lines to explicit, legally accurate terminal pairs
    valid_routes = [
        {"line": "1 Line Commuter", "origin": "South Ferry", "destination": "Van Cortlandt Park", "lat_range": (40.701, 40.889), "lon_range": (-74.013, -73.898)},
        {"line": "1 Line Commuter", "origin": "Van Cortlandt Park", "destination": "South Ferry", "lat_range": (40.701, 40.889), "lon_range": (-74.013, -73.898)},
        {"line": "A Line Commuter", "origin": "Utica Ave", "destination": "Inwood - 207 St", "lat_range": (40.668, 40.868), "lon_range": (-74.004, -73.931)},
        {"line": "A Line Commuter", "origin": "Inwood - 207 St", "destination": "Utica Ave", "lat_range": (40.668, 40.868), "lon_range": (-74.004, -73.931)},
        {"line": "Q Line Commuter", "origin": "Coney Island - Stillwell Ave", "destination": "96 St - 2 Ave", "lat_range": (40.577, 40.784), "lon_range": (-73.981, -73.951)},
        {"line": "Q Line Commuter", "origin": "96 St - 2 Ave", "destination": "Coney Island - Stillwell Ave", "lat_range": (40.577, 40.784), "lon_range": (-73.981, -73.951)},
        {"line": "R Line Commuter", "origin": "Bay Ridge - 95 St", "destination": "Forest Hills - 71 Ave", "lat_range": (40.618, 40.722), "lon_range": (-74.031, -73.844)},
        {"line": "R Line Commuter", "origin": "Forest Hills - 71 Ave", "destination": "Bay Ridge - 95 St", "lat_range": (40.618, 40.722), "lon_range": (-74.031, -73.844)}
    ]
    
    simulated_timetable = []
    now = datetime.now()
    current_offset_minutes = 2 
    
    for i in range(8):
        current_offset_minutes += random.randint(6, 14)
        random_seconds = random.randint(0, 59)

        arrival_dt = now + timedelta(minutes=current_offset_minutes, seconds=random_seconds)
        
        # FIX: Added a realistic 6-to-11 minute turnaround/dwell window for terminal processing
        turnaround_dwell_time = random.randint(6, 11)
        departure_dt = arrival_dt + timedelta(minutes=turnaround_dwell_time)
        
        route_profile = random.choice(valid_routes)
        
        simulated_timetable.append({
            'id': f"{random.randint(100000, 999999)}",
            'line': route_profile['line'],
            'origin': route_profile['origin'],
            'destination': route_profile['destination'],
            'arrival_timestamp': int(arrival_dt.timestamp()),
            'departure_timestamp': int(departure_dt.timestamp()),
            'arrival': arrival_dt.strftime("%I:%M:%S %p"),
            'departure': departure_dt.strftime("%I:%M:%S %p"),
            'lat': random.uniform(*route_profile['lat_range']),
            'lon': random.uniform(*route_profile['lon_range'])
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
                if not isinstance(overridden_trains, list):
                    overridden_trains = []
                    
                updated_overrides = []
                now_ts = int(time.time())
                
                for train in overridden_trains:
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
                r.delete('mta-admin-override')
    except Exception as redis_err:
        print(f"⚠️ Redis connection glitch encountered in main processing thread: {redis_err}")

    try:
        now_ts = int(time.time())
        enriched = []
        for t in live_timetable[:10]:
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

            t['eta_seconds'] = at - now_ts

            if 'departure_timestamp' not in t or not t['departure_timestamp']:
                t['departure_timestamp'] = at + 480

            try:
                t['arrival'] = datetime.fromtimestamp(at).strftime("%I:%M:%S %p")
            except Exception:
                t['arrival'] = datetime.fromtimestamp(now_ts + t['eta_seconds']).strftime("%I:%M:%S %p")

            enriched.append(t)

        r.set('mta-live-schedule', json.dumps(enriched))
    except Exception as write_err:
        print(f"⚠️ Unable to sync live schedule matrix: {write_err}")
        
    time.sleep(1)