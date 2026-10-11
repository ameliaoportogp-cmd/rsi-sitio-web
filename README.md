# Sitio corporativo RSI

Sitio corporativo responsivo para RSI (Repuestos, Suministros e Importaciones), construido con HTML5, Tailwind CSS compilado, JavaScript moderno y un endpoint PHP protegido para las solicitudes de cotización.

## Vista local

Para revisar el diseño puede ejecutar un servidor estático desde esta carpeta:

```bash
python3 -m http.server 8080
```

Luego visite `http://localhost:8080`.

El envío del formulario requiere PHP 7.4 o superior y debe probarse en un servidor compatible.

## Desarrollo

```bash
npm install
npm run build:css
```

## Producción

- Dominio: `https://www.rsirepuestos.com`
- Solicitudes comerciales: `ventas@rsirepuestos.com`
- Contacto general: `info@rsirepuestos.com`
- Teléfono y WhatsApp: `+503 6845-7930`

Publique `index.html`, `productos-temperatura.html`, `script.js`, `styles.css`, `contact.php`, `.htaccess` y la carpeta `assets/` en la raíz web del dominio. Mantenga habilitados HTTPS, ModSecurity y las políticas SPF/DKIM del correo.
