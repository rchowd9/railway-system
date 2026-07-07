package main

import (
	"sync"
	"time"
)

type TrainState struct {
	TrainNumber     string  `json:"train_number"`
	CurrentDistance float64 `json:"current_distance"`
	TotalDistance   float64 `json:"total_distance"`
	Velocity        float64 `json:"velocity"` // units/min
	AdminDelayMins  float64 `json:"admin_delay_minutes"`
	ETAWithDelay    float64 `json:"eta_minutes"`
	Status          string  `json:"status"`
}

type TrainTracker struct {
	Trains map[string]*TrainState
	mu     sync.RWMutex
}

func NewTrainTracker() *TrainTracker {
	tt := &TrainTracker{
		Trains: make(map[string]*TrainState),
	}
	// Seed demo tracks for execution visualization
	tt.Trains["METRO-A-99"] = &TrainState{TrainNumber: "METRO-A-99", CurrentDistance: 5.0, TotalDistance: 60.0, Velocity: 1.1, Status: "On Time"}
	tt.Trains["EXPRESS-B-02"] = &TrainState{TrainNumber: "EXPRESS-B-02", CurrentDistance: 12.0, TotalDistance: 85.0, Velocity: 1.5, Status: "On Time"}
	return tt
}

func (tt *TrainTracker) ExecSimulationLoop() {
	ticker := time.NewTicker(TickerInterval)
	for range ticker.C {
		tt.mu.Lock()
		for _, train := range tt.Trains {
			if train.CurrentDistance < train.TotalDistance && train.Status != "Halted" {
				// Progress step matching ticker time step
				train.CurrentDistance += train.Velocity * 0.016
				train.ETAWithDelay = CalculatePredictiveETA(train.CurrentDistance, train.TotalDistance, train.Velocity, train.AdminDelayMins)
			} else if train.CurrentDistance >= train.TotalDistance {
				train.Status = "Arrived"
				train.ETAWithDelay = 0
			}
		}
		tt.mu.Unlock()
	}
}

func (tt *TrainTracker) UpdateStateFromAdmin(trainNum string, delay float64, status string) {
	tt.mu.Lock()
	defer tt.mu.Unlock()
	if train, exists := tt.Trains[trainNum]; exists {
		train.AdminDelayMins = delay
		train.Status = status
	}
}