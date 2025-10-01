<?php
namespace App\Controllers;

use App\Models\m_main;

class c_main {
    public function index() {
        $model = new m_main();
        $message = $model->getMessage();

        $twig = $GLOBALS['twig'];
        echo $twig->render('main.twig', [
            'items' => $message
        ]);
    }
}