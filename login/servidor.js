 const express = require('express')
 const path = require('path');
 const app = express()
 const port = 3000
 
// Servir archivos estáticos (como login.html)
app.use(express.static(__dirname));


// Redirigir la raíz "/" a login.html
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'login.html'));
});

app.listen(port, () => {
console.log(`Servidor corriendo en http://localhost:${port}`);
});