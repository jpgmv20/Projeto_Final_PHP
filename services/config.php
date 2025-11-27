<?php

function connect_mysql()
{
    $localhost="localhost";
    $usuario="root";
    $senha="";//mudar para Cefet123, #Black7227,25022008
    $banco="robozzle";

    $mysqli= new mysqli($localhost,$usuario,$senha,$banco); // -> nao é pratico.  Vai estar abrindo e fechando conexao toda santa hora

    if($mysqli->connect_errno)
    {
        echo 'Falha ao conectar: (' . $mysqli->connect_errno . ')' . $mysqli->connect_error;
    }

    return $mysqli;

}

function create_user($nome, $email, $password_hash, $avatar_url, $config_json)
{
    $mysqli = connect_mysql();

    $stmt = $mysqli->prepare("CALL create_user(?, '', ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $nome, $email, $password_hash, $avatar_url, $config_json);

    $stmt->execute();
    $stmt->close();
    echo 'sucesso';
}







function PHPconsole($data) 
{
    //console.log versão php
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Console: " . $output . "' );</script>";
} 

?>