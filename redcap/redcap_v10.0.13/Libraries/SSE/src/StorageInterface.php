<?php
namespace REDCap\SSE;

interface StorageInterface
{
    public function __construct($channel);
    public function set($data);
    public function add($data);
    public function get();
    public function delete();
}