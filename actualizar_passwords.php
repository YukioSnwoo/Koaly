<?php
$host = 'localhost';
$dbname = 'u772860605_DATAK';
$user = 'u772860605_JACEUF';
$pass = 'j.2.4.6.4.R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Lista de usuarios con su contraseña actual EN TEXTO PLANO
    $usuarios = [
        ['email' => 'angelmoises549@gmail.com', 'password' => '123'],
        ['email' => 'core.armored@tienda.com', 'password' => '22_22'],
        ['email' => 'gerente.norte@tienda.com', 'password' => 'hash_demo'],
        ['email' => 'cajero1.centro@tienda.com', 'password' => 'hash_demo']
    ];

    foreach ($usuarios as $u) {
        $hash = password_hash($u['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE Usuarios SET contrasena_hash = :hash WHERE email = :email");
        $stmt->execute([':hash' => $hash, ':email' => $u['email']]);
        echo "Actualizado: " . $u['email'] . " con contraseña: " . $u['password'] . "<br>";
    }
    echo "¡Listo! Ahora hasheaste todas las contraseñas.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>