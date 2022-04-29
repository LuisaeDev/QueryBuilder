<?php

namespace LuisaeDev\QueryBuilder;

class RawValue {

	/** @var string Raw value definition */
	private $definition = '';

	/**
	 * Constructor.
	 *
	 * @param string $definition Raw value definition
	 */
	public function __construct(string $definition)
	{
		$this->definition = $definition;
	}

	/**
	 * Return the value definition.
	 *
	 * @return string
	 */
	public function get()
	{
		return $this->definition;
	}
}
?>
