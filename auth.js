function requireRol(rolEsperado) {
    let usuario = null;

    try {
        usuario = JSON.parse(sessionStorage.getItem('usuario'));
    } catch (error) {
        usuario = null;
    }

    if (!usuario || Number(usuario.id_rol) !== Number(rolEsperado)) {
        window.location.href = '../index.php';
        return null;
    }

    return usuario;
}

function logout() {
    sessionStorage.removeItem('usuario');
    window.location.href = '../index.php';
}
