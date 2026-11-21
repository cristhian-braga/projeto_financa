<?php
require_once __DIR__ . '/desenvolvimento/classes/util/ConfigLoader.php';

session_start();

$do     = isset($_GET['do']) ? $_GET['do'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

if (!$do || !$action) {
    die('Parâmetros do/action obrigatórios.');
}

$path = '/' . $do;
$config = ConfigLoader::get();

if (!isset($config[$path])) {
    die('Módulo não encontrado: ' . htmlspecialchars($do));
}

$mapping = $config[$path];

// instancia Form, se houver
$form = null;
if (!empty($mapping['form'])) {
    $formClass = self::resolveClassName($mapping['form']);
    $form = new $formClass();
    $form->preencher($_REQUEST); // transfere dados da requisição pro Form
}

// instancia Action
$actionClass = str_replace('classes.action.', '', $mapping['type']);
require_once __DIR__ . '/desenvolvimento/classes/action/' . $actionClass . '.php';
$actionInstance = new $actionClass();
if ($form) {
    $actionInstance->setForm($form);
}

if (!method_exists($actionInstance, $action)) {
    die('Ação não encontrada: ' . htmlspecialchars($action));
}

$forwardName = $actionInstance->$action();

// resolve forward
if (!isset($mapping['forwards'][$forwardName])) {
    die('Forward não encontrado: ' . htmlspecialchars($forwardName));
}

$forward = $mapping['forwards'][$forwardName];

if ($forward['redirect']) {
    header('Location: ' . $forward['path']);
    exit;
}

$smarty = SmartyFactory::getInstance();
$smarty->display($forward['path']);