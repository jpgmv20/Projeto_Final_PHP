
$localhost="localhost";
$usuario="root";
$senha="Cefet123";
$banco="robozzle";


$conexao=mysqli_connect($localhost,$usuario,$senha,$banco);
if(mysqli_error($conexao))
{
    exit("Status 500: Erro ao conectar com banco de dados: " . mysqli_connect_error());
}

