package main

import "time"

const (
	RedisAddr      = "127.0.0.1:6379"
	RedisChannel   = "train_admin_updates"
	ServerPort     = ":8080"
	TickerInterval = 1 * time.Second
	StreamThrottle = 500 * time.Millisecond
)