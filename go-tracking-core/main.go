package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"

	"github.com/go-redis/redis/v8"
)

var ctx = context.Background()

func main() {
	tracker := NewTrainTracker()
	go tracker.ExecSimulationLoop()

	rdb := redis.NewClient(&redis.Options{Addr: RedisAddr})
	go func() {
		pubsub := rdb.Subscribe(ctx, RedisChannel)
		defer pubsub.Close()
		for {
			msg, err := pubsub.ReceiveMessage(ctx)
			if err != nil {
				continue
			}
			var data struct {
				TrainNumber string  `json:"train_number"`
				DelayMins   float64 `json:"delay_minutes"`
				Status      string  `json:"status"`
			}
			if err := json.Unmarshal([]byte(msg.Payload), &data); err == nil {
				tracker.UpdateStateFromAdmin(data.TrainNumber, data.DelayMins, data.Status)
			}
		}
	}()

	http.HandleFunc("/ws/live", func(w http.ResponseWriter, r *http.Request) {
		StreamBroadcast(w, r, tracker)
	})

	fmt.Printf("Systems Engine active on port %s\n", ServerPort)
	log.Fatal(http.ListenAndServe(ServerPort, nil))
}