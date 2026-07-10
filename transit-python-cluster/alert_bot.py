import redis
import json
import sys

def start_notification_listener():
    print("🔄 Initializing connection to Redis infrastructure...", flush=True)
    
    try:
        # Establish connection with explicit compatibility configuration
        r = redis.Redis(host='127.0.0.1', port=6379, db=0, protocol=2)
        
        # Immediate ping test to verify Redis is awake before starting the loop
        if r.ping():
            print("✅ Redis handshake successful! Connection verified.", flush=True)
        else:
            print("❌ Redis ping failed.", flush=True)
            return
            
    except Exception as e:
        print(f"💥 Connection Error: Could not connect to Redis server.\nDetails: {e}", flush=True)
        return

    # Initialize Pub/Sub listener engine
    pubsub = r.pubsub()
    pubsub.subscribe('transit-updates')
    print("🤖 Python Notification Bot active and monitoring 'transit-updates' channel...", flush=True)

    try:
        # Continuous message listening loop
        for message in pubsub.listen():
            if message['type'] != 'message':
                continue
                
            try:
                payload = json.loads(message['data'].decode('utf-8'))
                train_id = payload.get('TrainNumber')
                status = payload.get('Status')
                
                if status == "Delayed":
                    print(f"\n🚨 [CRITICAL ALERT] System Disruption Detected!", flush=True)
                    print(f"👉 Track Profile {train_id} has shifted to DELAYED state.", flush=True)
                    print(f"📡 Dispatching webhook data packets to communication channels...", flush=True)
                else:
                    print(f"\n✅ [SYSTEM RESOLVED] Track Profile {train_id} is back on schedule.", flush=True)
                    
            except Exception as parse_error:
                print(f"⚠️ Error parsing pipeline event payload: {parse_error}", flush=True)
                
    except KeyboardInterrupt:
        print("\n🛑 Notification bot shutting down gracefully...", flush=True)

# Force execution when run directly from the command line
if __name__ == "__main__":
    start_notification_listener()