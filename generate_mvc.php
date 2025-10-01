<?php
if ($argc < 2) {
    echo "Usage: php generate_mvc.php [name] [[dir]]\n";
    exit(1);
}
if ($argc == 3) {
    $inputDir = strtolower($argv[2]);
    $inputDir = str_replace(['\\', '/'], '/', $inputDir);
    $inputDir = trim($inputDir, '/');
    $wGroupDir = $inputDir === '' ? '' : $inputDir . '/';
    $wClassGroupDir = $inputDir === '' ? '' : '\\' . str_replace('/', '\\', $inputDir);
} else {
    $wGroupDir = '';
    $wClassGroupDir = '';
}

$name = strtolower($argv[1]); // ファイル名・クラス名とも小文字
$baseDir = __DIR__ . '/app';
$paths = [
    'controller' => __DIR__ . "/app/controllers/{$wGroupDir}c_{$name}.php",
    'model'      => __DIR__ . "/app/models/{$wGroupDir}m_{$name}.php",
    'view'       => __DIR__ . "/app/views/{$wGroupDir}{$name}.twig",
    'css'        => __DIR__ . "/public/css/{$wGroupDir}{$name}.css",
    'js'         => __DIR__ . "/public/js/{$wGroupDir}{$name}.js"
];

 $template = [
'controller' => <<<PHP
<?php
namespace App\Controllers{$wClassGroupDir};

use App\Models{$wClassGroupDir}\m_{$name};

class c_{$name} {
    public function index() {
        \$model = new m_{$name}();
        \$message = \$model->getMessage();

        \$twig = \$GLOBALS['twig'];
        echo \$twig->render('{$wGroupDir}{$name}.twig', [
            'items' => \$message
        ]);
    }
}
PHP,

'model' => <<<PHP
<?php
namespace App\Models{$wClassGroupDir};

class m_{$name} {
    public function getMessage(): string {
        return "これは {$name} モデルからのメッセージです。";
    }
}
PHP,

'view' => <<<TWIG
{# ベースとなるテンプレートを継承する #}
{% extends 'base.twig' %}

{#- HTML_HEAD ----------------------------------- #}
{% block HTML_HEAD %}
{{ mapHead | raw }}
{% endblock %}

{#- BODY_HEADER ----------------------------------- #}
{% block BODY_HEADER %}
{% endblock %}

{#- BODY_MAIN ----------------------------------- #}

{% block BODY_MAIN %}
{{ items }}
{% endblock %}

{#- BODY_FOOTER ----------------------------------- #}
{% block BODY_FOOTER %}
{% endblock %}

TWIG,

'css' => <<<CSS
/* cssファイル */

CSS,

'js' => <<<JS
// jsファイル

JS


];

// ディレクトリ作成とファイル生成
foreach ($paths as $type => $path) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    if (!file_exists($path)) {
        file_put_contents($path, $template[$type]);
        echo "Created: $path\n";
    } else {
        echo "Already exists: $path\n";
    }
}
