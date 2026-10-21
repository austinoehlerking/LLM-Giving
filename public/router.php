<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(in_array($path,['/assets/app.css','/assets/library.css','/assets/app.js','/assets/analytics.js','/assets/submission.css','/assets/submission.js','/assets/request-setup.js','/assets/workflow.css','/assets/blog.css','/assets/workflow.js','/assets/artifacts.css','/assets/artifacts.js','/assets/home-hero.css','/assets/home-hero.js','/assets/research-community.webp','/assets/llm-giving-social-v1.jpg','/favicon.svg'],true)) return false;
require __DIR__.'/index.php';
