<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
$q_type = $_REQUEST['q_type'];
$helpersInstance = new helpers();

if ($q_type == "models_list") {
    $models = [
        [
            "name" => "John Doe",
            "description" => "Software Engineer",
            "guid" => 73,
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Jane Smith",
            "description" => "Graphic Designer",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ]
    ];

    echo json_encode($models);
    die();
}
