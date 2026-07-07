package main

// CalculatePredictiveETA applies a linear vector calculation to estimate remaining minutes
// accounts for live performance velocity drop off or static operational delays injected by admin.
func CalculatePredictiveETA(currentDist, totalDist, currentVelocity, adminDelayMins float64) float64 {
	if currentDist >= totalDist {
		return 0.0
	}
	if currentVelocity <= 0.0 {
		return 999.0 // Infinite delay catch block if train completely halts
	}

	remainingDistance := totalDist - currentDist
	baselineMinutes := remainingDistance / currentVelocity

	// Combine real-world physical trajectory with structural delays from administration
	return baselineMinutes + adminDelayMins
}