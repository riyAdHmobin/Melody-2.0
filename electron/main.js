'use strict';

const { app, BrowserWindow, shell, dialog } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const net  = require('net');
const fs   = require('fs');
const os   = require('os');

const CONFIG_FILE = path.join(os.homedir(), '.config', 'melody', '.env');
const APP_ROOT    = path.join(__dirname, '..');

let phpProcess = null;

function loadConfig() {
    const env = {};
    try {
        fs.readFileSync(CONFIG_FILE, 'utf8').split('\n').forEach(line => {
            const m = line.match(/^([A-Z_][A-Z0-9_]*)=(.+)$/);
            if (m) env[m[1]] = m[2].trim();
        });
    } catch { /* no config yet */ }
    return env;
}

function getFreePort() {
    return new Promise((resolve, reject) => {
        const srv = net.createServer();
        srv.listen(0, '127.0.0.1', () => {
            const { port } = srv.address();
            srv.close(() => resolve(port));
        });
        srv.on('error', reject);
    });
}

function waitReady(port, attempts = 30) {
    return new Promise((resolve, reject) => {
        const try_ = () => {
            const sock = net.createConnection(port, '127.0.0.1');
            sock.on('connect', () => { sock.destroy(); resolve(); });
            sock.on('error', () => {
                if (--attempts <= 0) return reject(new Error('PHP server did not start'));
                setTimeout(try_, 200);
            });
        };
        try_();
    });
}

async function createWindow() {
    const config = loadConfig();

    if (!config.DB_HOST) {
        dialog.showErrorBox(
            'Melody — Setup required',
            `No database configuration found.\n\nExpected: ${CONFIG_FILE}\n\nRun the installer again or create the file manually.`
        );
        app.quit();
        return;
    }

    const port = await getFreePort();

    phpProcess = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', APP_ROOT], {
        env: { ...process.env, ...config, PHP_CLI_SERVER_WORKERS: '4' },
        stdio: 'ignore',
    });

    phpProcess.on('error', err => {
        dialog.showErrorBox(
            'PHP not found',
            `Could not start PHP: ${err.message}\n\nInstall it with:\n  sudo apt install php-cli php-mysql`
        );
        app.quit();
    });

    phpProcess.on('close', code => {
        if (code !== 0 && code !== null && !app.isQuitting) {
            dialog.showErrorBox('PHP exited', `PHP server stopped unexpectedly (exit code ${code}).`);
            app.quit();
        }
    });

    await waitReady(port);

    const win = new BrowserWindow({
        width: 1280,
        height: 800,
        minWidth: 900,
        minHeight: 600,
        title: 'Melody',
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
        },
    });

    win.setMenuBarVisibility(false);
    win.loadURL(`http://127.0.0.1:${port}`);

    win.webContents.setWindowOpenHandler(({ url }) => {
        if (!url.startsWith(`http://127.0.0.1:${port}`)) {
            shell.openExternal(url);
            return { action: 'deny' };
        }
        return { action: 'allow' };
    });
}

app.on('before-quit', () => { app.isQuitting = true; });

app.on('window-all-closed', () => {
    phpProcess?.kill();
    app.quit();
});

app.whenReady().then(createWindow);
