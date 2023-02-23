<?php

    require 'ncc';
    import('net.nosial.configlib');

    $config = new \ConfigLib\Configuration('test');

    $config->setDefault('database.host', '127.0.0.1');
    $config->setDefault('database.port', 3306);
    $config->setDefault('database.username', 'root');
    $config->setDefault('database.password', null);
    $config->setDefault('database.name', 'test');