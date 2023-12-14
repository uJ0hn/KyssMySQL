<?php


if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function kyssmysql_MetaData()
{
    return array(
        'DisplayName' => 'KyssMySQL',
        'APIVersion' => '1.0',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '3306',
        'DefaultSSLPort' => '3306',
    );
}

function kyssmysql_ConfigOptions() {

    $configarray = array (
        "PhPMyAdmin URL (Optional - Leave blank)" => array( "Type" => "text", "Default" => "https://example.com/phpmyadmin"),
        "User Prefix (Required)" => array("Type" => "text", "Default" => "user_"),
        "Database Prefix (Required)" => array("Type" => "text", "Default" => "my_"),
    );

    return $configarray;
}

function kyssmysql_getPrefix($params) {
    $prefix1 = $params["configoption2"]." " . $params["configoption3"];
    return explode(" ", $prefix1);
}





function kyssmysql_connectAndExecute($action, $params) {

    $server = $params["serverhostname"];
    $user = $params["serverusername"];
    $pwd = $params["serverpassword"];

    try {
        $pdo = new PDO("mysql:host=$server:". $params['serverport'] .";", $user, $pwd);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        return "Erro na conexão: " . $e->getMessage();
    }
    if($params['model']->serviceProperties->get('MySQL User') != '') {
        $user = $params['model']->serviceProperties->get('MySQL User');
        $database = $params['model']->serviceProperties->get('MySQL DB');
    } else {
        $randomid = kyssmysql_randomId();
        $user = kyssmysql_getPrefix($params)[0] . "" . $randomid;
        $database = kyssmysql_getPrefix($params)[1] . "" . $randomid;
    }

    $params['model']->serviceProperties->save(['MySQL User' => $user]);
    $params['model']->serviceProperties->save(['MySQL DB' => $database]);

    if($action == "create") {
        $sql = "CREATE USER '$user'@'%' IDENTIFIED BY '" . $params["password"] . "';";
        $sql2 = "CREATE DATABASE " . $database . ";";
        $sql3 = "GRANT ALL PRIVILEGES ON " . $database . ".* to '" . $user . "'@'%';";
        $sql4 = "flush privileges;";
        try {
            $pdo->query($sql);
            $pdo->query($sql2);
            $pdo->query($sql3);
            $pdo->query($sql4);
            update_query("tblhosting",array(
                "diskusage"=>'0',
                "disklimit"=>'0',
                "bwusage"=>'0',
                "bwlimit"=>'0',
                "lastupdate"=>"now()",
            ),array("server"=>$params['serverid'], "orderid"=>$params['model']['orderid']));

        } catch (PDOException $e) {
            return "Erro: $sql $sql2 $sql3 $sql4" . $e->getMessage();
        }
    } else if($action == "terminate") {
        $sql = "DROP USER '" . $user . "'@'%';";
        $sql2 = "DROP DATABASE " . $database . ";";
        try {
            $pdo->query($sql);
            $pdo->query($sql2);
        } catch (PDOException $e) {
            return "Erro: " . $e->getMessage();
        }
    } else if($action == "changepass") {
        $sql = "ALTER USER '" . $user . "'@'%' IDENTIFIED BY '" . $params["password"] . "';";
        $sql2 = "flush privileges;";
        try {
            $pdo->query($sql);
            $pdo->query($sql2);
        } catch (PDOException $e) {
            return "Erro: " . $e->getMessage();
        }
        return "success";
    } else if($action == "SuspendAccount") {
        $sql = "DROP USER '" . $user . "'@'%';";
        try {
            $pdo->query($sql);
        } catch (PDOException $e) {
            return "Erro: " . $e->getMessage();
        }
    } else if($action == "UnsuspendAccount") {
        $sql = "CREATE USER '$user'@'%' IDENTIFIED BY '" . $params["password"] . "';";
        $sql2 = "GRANT ALL PRIVILEGES ON " . $database . ".* to '" . $user . "'@'%';";
        try {
            $pdo->query($sql);
            $pdo->query($sql2);
        } catch (PDOException $e) {
            return "Erro: " . $e->getMessage();
        }
    }


    return "success";
}


function kyssmysql_CreateAccount($params) {
    return kyssmysql_connectAndExecute("create", $params);
}

function kyssmysql_TerminateAccount($params) {
    return kyssmysql_connectAndExecute("terminate", $params);
}

function kyssmysql_ChangePassword($params) {
    return kyssmysql_connectAndExecute("changepass", $params);
}

function kyssmysql_randomId() {
    $lettersAndDigits = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $random = mt_rand();
    $sb = "";

    for ($i = 0; $i < 5; $i++) {
        $sb .= $lettersAndDigits[$random % strlen($lettersAndDigits)];
        $random = mt_rand();
    }

    return $sb;
}


function kyssmysql_TestConnection($params) {
    $server = $params["serverhostname"];
    $user = $params["serverusername"];
    $pwd = $params["serverpassword"];

    $success = true;
    $errorMsg = '';
    try {
        $pdo = new PDO("mysql:host=$server;", $user, $pwd);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $success = false;
        $errorMsg = $e->getMessage();
    }


    return array(
        'success' => $success,
        'error' => $errorMsg,
    );

}

function kyssmysql_SuspendAccount($params) {
    kyssmysql_connectAndExecute('SuspendAccount', $params);
}

function kyssmysql_UnsuspendAccount($params) {
    kyssmysql_connectAndExecute('UnsuspendAccount', $params);
}

function kyssmysql_UsageUpdate($params) {
    $server = $params["serverhostname"];
    $user = $params["serverusername"];
    $pwd = $params["serverpassword"];

    $userdb = $params['model']->serviceProperties->get('MySQL User');
    $database = $params['model']->serviceProperties->get('MySQL DB');


    $pdo = new PDO("mysql:host=$server;", $user, $pwd);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT SUM(data_length + index_length) AS tamanho_total FROM information_schema.tables WHERE table_schema = '" . $database . "'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $tamanho_total_bytes = $row["tamanho_total"];
    $tamanho_total_mb = round($tamanho_total_bytes / (1024 * 1024), 2);



    update_query("tblhosting",array(
        "diskusage"=>$tamanho_total_mb,
        "disklimit"=>'0',
        "bwusage"=>'0',
        "bwlimit"=>'0',
        "lastupdate"=>"now()",
    ),array("server"=>$params['serverid'], "orderid"=>$params['model']['orderid']));

}


function kyssmysql_ClientArea($params) {


    $user = $params['model']->serviceProperties->get('MySQL User');
    $database = $params['model']->serviceProperties->get('MySQL DB');

    $phpmyadminurl = '';
    if($params["configoption1"] != "") {
        $phpmyadminurl = '<a href="'. $params["configoption1"]. '" class="btn btn-default" target="_blank">PhpMyAdmin</a>';
    }


    $code = '<form  target="_blank">
<div class="row">
<div class="col-sm-5 text-right">
<strong>Server</strong>
</div>
<div class="col-sm-7 text-left">
'.$params["serverhostname"].'
</div>
</div>
<div class="row">
<div class="col-sm-5 text-right">
<strong>Database</strong>
</div>
<div class="col-sm-7 text-left">
'.$database.'
</div>
</div>
<div class="row">
<div class="col-sm-5 text-right">
<strong>User</strong>
</div>
<div class="col-sm-7 text-left">
'.$user.'
</div>
</div>	
<div class="row">
<div class="col-sm-5 text-right">
<strong>Password</strong>
</div>
<div class="col-sm-7 text-left">
'.$params["password"].'
</div>
</div>
<br>	

'.$phpmyadminurl. '
</form>';
    return $code;

}



?>