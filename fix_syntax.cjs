const fs = require('fs');

const path = require('path');

function walkDir(dir, callback) {
    fs.readdirSync(dir).forEach(f => {
        let dirPath = path.join(dir, f);
        let isDirectory = fs.statSync(dirPath).isDirectory();
        isDirectory ? walkDir(dirPath, callback) : callback(path.join(dir, f));
    });
}

walkDir('d:/WebApps/swmrf/app/Filament', function(filePath) {
    if (filePath.endsWith('.php')) {
        let content = fs.readFileSync(filePath, 'utf8');
        let newContent = content.replace(/\s*->formats\(\[\\Filament\\Actions\\Exports\\Enums\\ExportFormat::Xlsx\]\),/g, '');
        if (content !== newContent) {
            fs.writeFileSync(filePath, newContent);
            console.log('Fixed:', filePath);
        }
    }
});
