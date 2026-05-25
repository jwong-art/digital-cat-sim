const { app, BrowserWindow, dialog } = require('electron');
const path = require('path');
const { spawn, execSync } = require('child_process');

let mainWindow;
let phpServer;

function findPhp() {
  const isDev = !app.isPackaged;
  if (isDev) {
    return 'php';
  }
  // 打包模式：优先使用内嵌 PHP，其次查找系统 PHP
  const embeddedPhp = path.join(process.resourcesPath, 'php', 'php');
  const fs = require('fs');
  if (fs.existsSync(embeddedPhp)) {
    return embeddedPhp;
  }
  // 尝试系统 PHP
  try {
    execSync('which php', { stdio: 'pipe' });
    return 'php';
  } catch (e) {
    return null;
  }
}

function getDocRoot() {
  const isDev = !app.isPackaged;
  if (isDev) {
    return __dirname;
  }
  return path.join(process.resourcesPath, 'app');
}

function startPhpServer() {
  const phpPath = findPhp();
  if (!phpPath) {
    dialog.showErrorBox(
      'PHP 未找到',
      '本应用需要 PHP 运行环境。请安装 PHP 8.0+ 后重试。\n\n' +
      'Ubuntu/Debian: sudo apt install php\n' +
      'macOS: brew install php\n' +
      'Windows: https://php.net/download'
    );
    app.quit();
    return null;
  }

  const docRoot = getDocRoot();
  const port = 18765;

  console.log(`Starting PHP server: ${phpPath} -S 127.0.0.1:${port} -t ${docRoot}`);

  phpServer = spawn(phpPath, ['-S', `127.0.0.1:${port}`, '-t', docRoot], {
    stdio: 'pipe'
  });

  phpServer.stderr.on('data', (data) => {
    console.log(`PHP: ${data}`);
  });

  phpServer.on('error', (err) => {
    console.error('PHP server error:', err);
    dialog.showErrorBox('PHP 启动失败', err.message);
    app.quit();
  });

  return port;
}

function createWindow(port) {
  mainWindow = new BrowserWindow({
    width: 800,
    height: 900,
    minWidth: 600,
    minHeight: 700,
    title: 'Digital Cat Sim - 电子宠物',
    icon: path.join(__dirname, 'favicon.ico'),
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload.js')
    }
  });

  mainWindow.loadURL(`http://127.0.0.1:${port}/index.html`);

  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  // 开发模式打开 DevTools
  if (!app.isPackaged) {
    mainWindow.webContents.openDevTools();
  }
}

app.on('ready', () => {
  const port = startPhpServer();
  if (port) {
    setTimeout(() => {
      createWindow(port);
    }, 1500);
  }
});

app.on('window-all-closed', () => {
  if (phpServer) {
    phpServer.kill();
  }
  app.quit();
});

app.on('before-quit', () => {
  if (phpServer) {
    phpServer.kill();
  }
});
