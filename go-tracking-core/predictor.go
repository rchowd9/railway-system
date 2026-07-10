package main

import "math"

type PredictorEngine struct {
	CongestionWeight float64
	TimeOfDayFactor  float64
}

func NewPredictorEngine() *PredictorEngine {
	return &PredictorEngine{
		CongestionWeight: 1.35,
		TimeOfDayFactor:  1.10,
	}
}

func (pe *PredictorEngine) PredictMinutesRemaining(currentDist, totalDist, currentSpeed float64, status string) int {
	remainingDist := totalDist - currentDist
	if remainingDist <= 0 || currentSpeed <= 0 {
		return 0
	}

	baseETA := (remainingDist / currentSpeed)

	if status == "Delayed" {
		baseETA = baseETA * pe.CongestionWeight
	} else if status == "SIGNAL HOLD" {
		return 99 
	}

	finalPredictedETA := baseETA * pe.TimeOfDayFactor

	return int(math.Ceil(finalPredictedETA))
}