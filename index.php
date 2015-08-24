<?php
/*
WebComposer (http://github.com/leoruhland/webcomposer)
This script contains many flaws and vulnerabilities and should be treated as experimental.
Any improvement in this matter will be welcome.
Thanks to Manan Jadhav (CurosMJ) creator of NoConsoleComposer (https://github.com/CurosMJ/NoConsoleComposer) that this script was based and still contain some fragments.
*/

$password = "w3bC0mp053R";

if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_PW'] !== $password) {
    header('WWW-Authenticate: Basic realm="WebComposer"');
    header('HTTP/1.0 401 Unauthorized');
    exit(0);
}

if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD']=='POST') {
    if (!isset($_POST['function'])){ die("You must specify a function"); }
    if(!file_exists('wc')){
        mkdir('wc', 0777, true);
        mkdir('wc/cache', 0777, true);
    } else {
        if(!file_exists('wc/cache')){
            mkdir('wc/cache', 0777, true);
        }
    }
    call($_POST['function']);
    exit(0);
}

function call($function="help"){
    switch($function){
        case 'getStatus':
        $output = array(
            'composer' => file_exists('composer.phar'),
            'composer_extracted' => file_exists('wc/extracted'),
            'installer' => file_exists('installer.php'),
        );
        header("Content-Type: text/json; charset=utf-8");
        echo json_encode($output);
        exit(0);
        break;

        case 'downloadComposer':
        $installerURL = 'https://getcomposer.org/installer';
        $installerFile = 'installer.php';
        if (!file_exists($installerFile)) {
            echo 'Downloading ' . $installerURL . PHP_EOL;
            flush();
            $ch = curl_init($installerURL);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_FILE, fopen($installerFile, 'w+'));
            if (curl_exec($ch)){
                echo 'Success downloading ' . $installerURL . PHP_EOL;
            } else {
                echo 'Error downloading ' . $installerURL . PHP_EOL;
                die();
            }
            flush();
        }
        echo 'Installer found : ' . $installerFile . PHP_EOL;
        echo 'Starting installation...' . PHP_EOL;
        flush();
        $argv = array();
        include($installerFile);
        flush();
        break;

        case 'extractComposer':
        if (file_exists('composer.phar')) {
            echo 'Extracting composer.phar ...' . PHP_EOL;
            flush();
            $composer = new Phar('composer.phar');
            $composer->extractTo('wc/extracted');
            echo 'Extraction complete.' . PHP_EOL;
        } else {
            echo 'composer.phar does not exist';
        }
        break;

        case 'command':
        set_time_limit(-1);
        putenv('COMPOSER_HOME=' . __DIR__ . '/wc/extracted/bin/composer');
        putenv('COMPOSER_CACHE_DIR=' . __DIR__ . '/wc/cache');
        if(!file_exists($_POST['path'])) {
            echo 'Invalid path: '.$_POST['path'];
            die();
        }
        if (file_exists('wc/extracted')) {
            require_once(__DIR__ . '/wc/extracted/vendor/autoload.php');
            $input = new Symfony\Component\Console\Input\StringInput($_POST['command'].' -'.$_POST['verbose'].' -d '.$_POST['path']);
            $output = new Symfony\Component\Console\Output\StreamOutput(fopen('php://output','w'));
            $app = new Composer\Console\Application();
            $app->run($input,$output);
        } else {
            echo 'Composer not extracted.';
            call('extractComposer');
        }
        break;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>WebComposer</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <script type="text/javascript" src="//code.jquery.com/jquery-2.1.1.min.js"></script>
    <script type="text/javascript">
    var lastCall = false;
    function composerNotice(msg)
    {
        $("#output").append("\n********************************************************************\n");
        $("#output").append("* "+msg);
        $("#output").append("\n********************************************************************\n\n");
        $("#output").scrollTop($("#output").prop("scrollHeight"));
    }

    function callComposer(formData)
    {
        lastCall = formData;
        composerNotice("WebComposer started: composer "+lastCall.command);

        $("#input").attr('disabled', 'disabled');

        var last_response_len = false;
        $.ajax('index.php', {
            method: 'POST',
            data: formData,
            xhrFields: {
                onprogress: function(e)
                {
                    console.log(e.currentTarget.response);
                    var this_response, response = e.currentTarget.response;
                    if(last_response_len === false)
                    {
                        this_response = response;
                        last_response_len = response.length;
                    }
                    else
                    {
                        this_response = response.substring(last_response_len);
                        last_response_len = response.length;

                    }
                    $("#output").append(this_response);
                    $("#output").scrollTop($("#output").prop("scrollHeight"));
                }
            }
        })
        .done(function(data)
        {
            composerNotice("WebComposer ended: composer "+lastCall.command);

            $("#input").val('');
            $("#input").removeAttr('disabled');
            $('#input').focus();

        })
        .fail(function(data)
        {
            composerNotice("WebComposer ended WITH ERRORS: composer "+lastCall.command);

            $("#input").val('');
            $("#input").removeAttr('disabled');
            $('#input').focus();
        });
        console.log('Request Sent');
        $("#output").scrollTop($("#output").prop("scrollHeight"));

    }

    function check(){
        $("#output").append('\nLoading...\n\n');
        $.post('./index.php',
        {
            "function": "getStatus",
            "password": $("#password").val()
        },
        function(data) {
            console.log(data);
            if (data.composer_extracted)
            {
                $("#output").append("Ready. All commands are available.\n\n");
                $("button").removeClass('disabled');
            }
            else if(data.composer)
            {
                $.post('./index.php',
                {
                    "password": $("#password").val(),
                    "function": "extractComposer",
                },
                function(data) {
                    $("#output").append(data);
                    check();
                }, 'text');
            }
            else
            {
                $("#output").append("Please wait till composer is being installed...\n");
                $.post('./index.php',
                {
                    "password": $("#password").val(),
                    "function": "downloadComposer",
                },
                function(data) {
                    $("#output").append(data);
                    check();
                }, 'text');
            }
        });

    }

    $(document).ready(function(){
        check();
        $('#input').focus();
        $('#output').on('click', function(){
            $('#input').focus();
        });
        $('#inputForm').on('submit', function(e){
            e.preventDefault();
            var formData = {
                "path": $("#path").val(),
                "verbose": $("#verbose").val(),
                "command": $('#input').val(),
                "function": "command"
            };
            callComposer(formData);
            return false;
        })

    });
    </script>
    <style>
    * {
        -webkit-box-sizing: border-box;
        -moz-box-sizing: border-box;
        box-sizing: border-box;
        padding:0;
        margin:0;
    }
    html, body {
        height:100%;
        width:100%;
        font-family: Menlo,Monaco,Consolas,"Courier New",monospace;
        background:#000;
        overflow:hidden;
    }
    #output
    {
        width:100%;
        position:absolute;
        top:0;
        bottom:34px;
        left:0;
        right:0;
        overflow-y:scroll;
        overflow-x:hidden;
        color: #00ff00;
        font-size:14px;
        margin:0;
        border-radius:0px;
        border: 0px;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        padding:20px;
        font-family: Menlo,Monaco,Consolas,"Courier New",monospace;

    }

    input, select {
        width:100%;
        background: #fff;
        border-radius:0px;
        border: 1px solid #333;
        outline: none;
        -webkit-box-shadow: none !important;
        -moz-box-shadow: none !important;
        box-shadow: none !important;
        padding:5px;
        font-size:14px;
        color:#000;
        font-family: Menlo,Monaco,Consolas,"Courier New",monospace;
    }

    #input {
        position:absolute;
        width:100%;
        height:100%;
        margin:0;
        top:0;
        color: #fff;
        border-radius:0px;
        border: 0px;
        background: transparent;
        outline: none;
        -webkit-box-shadow: none !important;
        -moz-box-shadow: none !important;
        box-shadow: none !important;
        padding-left:85px;
        font-size:14px;
        font-family: Menlo,Monaco,Consolas,"Courier New",monospace;
    }

    #inputForm {
        position:absolute;
        bottom:0;
        width:100%;
        height:34px;
        border-top: 1px solid #111;
        padding:0;
        background:#000;
    }

    #inputForm span {
        position:absolute;
        top:0;
        font-size:14px;
        padding:9px 10px;
        color:#fafafa;
        font-family: Menlo,Monaco,Consolas,"Courier New",monospace;
    }

    #configs {
        position:absolute;
        right:30px;
        max-width:300px;
        top:0;
        padding: 30px;
        background: #fff;
        height:auto;
        max-height:50%;
        transition: all .2s ease-out;
        transform: translate3d(0,-100%,0);
        opacity: 0.5;
        font-size:11px;
    }
    #configs:hover {
        transform: translate3d(0,0,0);
        opacity: 0.9;
        z-index:10;
    }
    #configs .aba {
        position: absolute;
        bottom:-30px;
        right:0px;
        width:50px;
        height:30px;
        padding:8px 5px;
        font-size:11px;
        color:#000;
        background: #fff;
    }

    </style>
</head>
<body>
    <pre id="output"></pre>
    <div id="configs">
        <div class="aba">config</div>
        <label for="path">Path</label>
        <input id="path" type="text" value="../<?php echo basename(__DIR__); ?>" />
        <br clear="all"/>
        <br clear="all"/>
        <label for="verbose">Verbose level</label>
        <select id="verbose">
            <option value="vvv" selected>-vvv (High verbose)</option>
            <option value="vv">-vv (Medium verbose)</option>
            <option value="v">-v (Low verbose)</option>
        </select>
    </div>
    <form id="inputForm" autocomplete="off">
        <span>composer</span>
        <input id="input" name="cmd" type="text" class="form-control" />
    </form>
</body>
</html>
