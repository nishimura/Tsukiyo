<?php

class Tsukiyo_Filter extends IteratorIterator
{
    private $callback;
    public function __construct(Traversable $iterator, $callback)
    {
        parent::__construct($iterator);
        $this->callback = $callback;
    }

    public function current()
    {
        $arg = parent::current();
        return call_user_func($this->callback, $arg);
    }
}
