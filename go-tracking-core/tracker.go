package main

import (
	"math"
	"sync"
	"time"
)

type Train struct {
	TrainNumber      string           `json:"train_number"`
	CurrentDistance  float64          `json:"current_distance"`
	TotalDistance    float64          `json:"total_distance"`
	Speed            float64          `json:"speed"`
	Status           string           `json:"status"`
	MinutesRemaining int              `json:"minutes_remaining"`
}

type TrainTracker struct {
	Trains []Train
	mu     sync.RWMutex // Changed from sync.Mutex to sync.RWMutex to support RLock/RUnlock
}

// Added the missing constructor function that main.go is looking for
func NewTrainTracker() *TrainTracker {
	return &TrainTracker{
		Trains: []Train{
			{TrainNumber: "EXPRESS-B-02", CurrentDistance: 0.0, TotalDistance: 50.0, Speed: 1.2, Status: "On Time"},
			{TrainNumber: "METRO-A-99", CurrentDistance: 10.0, TotalDistance: 40.0, Speed: 0.9, Status: "On Time"},
		},
	}
}

func (tt *TrainTracker) StartSimulationLoop() {
	ticker := time.NewTicker(1 * time.Second)
	predictor := NewPredictorEngine()

	for range ticker.C {
		tt.mu.Lock()
		
		for i := range tt.Trains {
			tt.Trains[i].CurrentDistance += tt.Trains[i].Speed / 60.0
			if tt.Trains[i].CurrentDistance >= tt.Trains[i].TotalDistance {
				tt.Trains[i].CurrentDistance = 0.0 
			}
		}

		if len(tt.Trains) >= 2 {
			distDelta := math.Abs(tt.Trains[0].CurrentDistance - tt.Trains[1].CurrentDistance)
			if distDelta < 1.5 {
				if tt.Trains[0].CurrentDistance > tt.Trains[1].CurrentDistance {
					tt.Trains[1].Status = "SIGNAL HOLD"
					tt.Trains[1].Speed = 0.0
				} else {
					tt.Trains[0].Status = "SIGNAL HOLD"
					tt.Trains[0].Speed = 0.0
				}
			} else {
				for i := range tt.Trains {
					if tt.Trains[i].Status == "SIGNAL HOLD" {
						tt.Trains[i].Status = "On Time"
						tt.Trains[i].Speed = 1.2
					}
				}
			}
		}

		for i := range tt.Trains {
			tt.Trains[i].MinutesRemaining = predictor.PredictMinutesRemaining(
				tt.Trains[i].CurrentDistance, 
				tt.Trains[i].TotalDistance, 
				tt.Trains[i].Speed, 
				tt.Trains[i].Status,
			)
		}
		
		tt.mu.Unlock()
	}
}