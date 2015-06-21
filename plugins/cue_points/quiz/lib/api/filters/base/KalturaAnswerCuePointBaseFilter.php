<?php
/**
 * @package plugins.quiz
 * @subpackage api.filters.base
 * @abstract
 */
abstract class KalturaAnswerCuePointBaseFilter extends KalturaCuePointFilter
{
	static private $map_between_objects = array
	(
	);

	static private $order_by_map = array
	(
		"+isCorrect" => "+is_correct",
		"-isCorrect" => "-is_correct",
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

	public function getOrderByMap()
	{
		return array_merge(parent::getOrderByMap(), self::$order_by_map);
	}
}
