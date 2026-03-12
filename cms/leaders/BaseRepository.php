<?php

class BaseRepository
{
    protected $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }
}
