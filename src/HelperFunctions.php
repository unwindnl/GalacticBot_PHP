<?php

namespace GalacticBot;

function array_direction(Array $ar) {
	$deltas = array_deltas($ar);
	$deltas = array_normalize($deltas);
	$delta = array_average($deltas);

	if ($delta >= 0.01)
		return 1;
	else if ($delta <= -0.01)
		return -1;
	else
		return 0;
}

function array_deltas(Array $ar) {
	$lastV = null;
	$deltas = [];

	foreach($ar AS $i => $v) {
		if ($i > 0) {
			$deltas[] = $v - $lastV;
		}

		$lastV = $v;
	}

	return $deltas;
}

function array_average($a) {
	if (count($a) == 0)
		return null;

	return array_sum($a) / count($a);
}

function array_normalize(Array $ar) {
	$max = null;

	foreach($ar as $v) {
		$v = abs($v);

		if ($max == null) {
			$max = $v;
		} else {
			$max = max($max, $v);
		}
	}

	if ($max > 0)
		foreach($ar as $i => $v)
			$ar[$i] = $v / $max;

	return $ar;
}

function forecast_direction(Array $real, Array $predicted, $windowSize = 60, $windowSizePredicted = 15) {
	$real = array_splice($real, -$windowSize);
	$predicted = array_splice($predicted, 0, $windowSizePredicted);
	$samples = array_merge($real, $predicted);

	/*
	var_dump("real = ", $real);
	var_dump("predicted = ", $predicted);
	var_dump("samples = ", $samples);
	echo "\n\n----\n\n";
	exit();
	*/

	return array_direction($samples);
}

