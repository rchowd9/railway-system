package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"time"
	"net/http"
	"os"

	"github.com/go-redis/redis/v8"
)

var ctx = context.Background()
var redisClient *redis.Client

func main() {
	redisAddr := os.Getenv("REDIS_ADDR")
	if redisAddr == "" {
		redisAddr = "localhost:6379"
	}

	redisClient = redis.NewClient(&redis.Options{
		Addr: redisAddr,
	})

	_, err := redisClient.Ping(ctx).Result()
	if err != nil {
		log.Fatalf("Fatal: Could not connect to Redis: %v", err)
	}
	fmt.Println("Successfully connected to Redis infrastructure.")

	tracker := NewTrainTracker()
	go tracker.StartSimulationLoop()

	// 🚨 ADD THIS BLOCK: Listen for administration overrides via Redis Pub/Sub
	go func() {
		pubsub := redisClient.Subscribe(ctx, "transit-updates")
		defer pubsub.Close()

		type StatusUpdate struct {
			TrainNumber string
			Status      string
		}

		for {
			msg, err := pubsub.ReceiveMessage(ctx)
			if err != nil {
				continue
			}

			var update StatusUpdate
			if err := json.Unmarshal([]byte(msg.Payload), &update); err == nil {
				tracker.mu.Lock()
				for i := range tracker.Trains {
					if tracker.Trains[i].TrainNumber == update.TrainNumber {
						tracker.Trains[i].Status = update.Status
						if update.Status == "Delayed" {
							tracker.Trains[i].Speed = 0.4 
						} else {
							tracker.Trains[i].Speed = 1.5 
						}
						
						// 🚨 NEW CACHING LOGIC: Save event log in Redis memory
						logEntry := fmt.Sprintf("[%s] %s set to %s", time.Now().Format("15:04:05"), update.TrainNumber, update.Status)
						redisClient.LPush(ctx, "transit-historical-logs", logEntry)
						redisClient.LTrim(ctx, "transit-historical-logs", 0, 49) // Cap list at 50 entries
					}
				}
				tracker.mu.Unlock()
			}
		}
	}()

	http.HandleFunc("/stream", func(w http.ResponseWriter, r *http.Request) {
		StreamBroadcast(w, r, tracker)
	})

	fmt.Println("Go Stream Engine listening on http://localhost:8080...")
	if err := http.ListenAndServe(":8080", nil); err != nil {
		log.Fatalf("Network boot error: %v", err)
	}
}