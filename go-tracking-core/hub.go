package main

import (
	"encoding/json"
	"net/http"
	"time"

	"github.com/gorilla/websocket"
)

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
	CheckOrigin:     func(r *http.Request) bool { return true }, // Bypasses CORS blockers
}

func StreamBroadcast(w http.ResponseWriter, r *http.Request, tracker *TrainTracker) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		return
	}
	defer conn.Close()

	for {
		tracker.mu.RLock()
		payload, err := json.Marshal(tracker.Trains)
		tracker.mu.RUnlock()

		if err != nil {
			break
		}

		if err := conn.WriteMessage(websocket.TextMessage, payload); err != nil {
			break
		}
		time.Sleep(1 * time.Second) // Dynamic stream update velocity throttle
	}
}