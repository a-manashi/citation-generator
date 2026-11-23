<?php

namespace Config;

class Filters extends \CodeIgniter\Config\Filters
{
    public array $required = [
        'before' => ['forcehttps'],
        'after' => ['secureheaders'],
    ];
}
