<?php

namespace App;

class MyClass
{
    private static int $numberStatic = 0;
    private int $number = 0;

    public function __construct()
    {
        print "Construct\n";
    }

    public function __destruct()
    {
        print "Deconstruct\n";
    }

    public function add(): void
    {
        self::$numberStatic++;
        $this->number++;
    }

    public function get(): string
    {
        return " ( " .self::$numberStatic . " - " . $this->number . " ) ";
    }
}
