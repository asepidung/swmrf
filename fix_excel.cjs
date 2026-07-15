const fs = require('fs');
const path = require('path');

function findFiles(dir, filter, fileList = []) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const filepath = path.join(dir, file);
        if (fs.statSync(filepath).isDirectory()) {
            findFiles(filepath, filter, fileList);
        } else if (filter.test(filepath)) {
            fileList.push(filepath);
        }
    }
    return fileList;
}

const adminDir = path.join(__dirname, 'app/Filament/Admin');
const files = findFiles(adminDir, /\.php$/);
const exporterDir = path.join(__dirname, 'app/Filament/Exports');

let modifiedCount = 0;

for (const file of files) {
    let content = fs.readFileSync(file, 'utf8');
    
    // We search for lines containing ExportAction::make('excel') and trace the block
    let updatedContent = content;
    
    const basicRegex = /([ \t]*)(?:\\?Filament\\)?(?:Tables\\)?(?:Actions\\)?ExportAction::make\(['"](excel|export_excel)['"]\)([\s\S]*?)->exporter\((.*?)::class\)([\s\S]*?)(?:->formats\(\[.*?\]\))?[,;]?/g;
    
    let changesMade = false;
    updatedContent = updatedContent.replace(basicRegex, (fullMatch, indent, actionName, betweenMakeAndExporter, exporterClassFull, afterExporter) => {
        // extract class name
        const exporterClassParts = exporterClassFull.split('\\');
        const exporterClassName = exporterClassParts[exporterClassParts.length - 1];
        
        // Find exporter file
        const exporterFile = path.join(exporterDir, exporterClassName + '.php');
        if (!fs.existsSync(exporterFile)) {
            console.log("Exporter not found:", exporterFile);
            return fullMatch;
        }
        
        const exporterContent = fs.readFileSync(exporterFile, 'utf8');
        
        // Extract columns
        const colRegex = /ExportColumn::make\(['"](.*?)['"]\)(?:->label\(['"](.*?)['"]\))?/g;
        let colMatch;
        let headers = [];
        let fields = [];
        
        while ((colMatch = colRegex.exec(exporterContent)) !== null) {
            const field = colMatch[1];
            const label = colMatch[2] || field;
            headers.push(label);
            
            // convert dots to null safe object access, e.g. customer.name -> customer?->name
            const phpField = field.split('.').map(p => p).join('?->');
            fields.push(`$record->${phpField} ?? ''`);
        }
        
        // Generate OpenSpout block
        const spoutBlock = `${indent}\\Filament\\Tables\\Actions\\Action::make('${actionName}')
${indent}    ->label(__('Excel'))
${indent}    ->icon('heroicon-o-document-text')
${indent}    ->color('success')
${indent}    ->action(function ($livewire) {
${indent}        $records = $livewire->getFilteredTableQuery()->get();
${indent}        return response()->streamDownload(function () use ($records) {
${indent}            $writer = new \\OpenSpout\\Writer\\XLSX\\Writer();
${indent}            $writer->openToFile('php://output');
${indent}            $writer->addRow(\\OpenSpout\\Common\\Entity\\Row::fromValues([
${indent}                ${headers.map(h => `'${h.replace(/'/g, "\\'")}'`).join(', ')}
${indent}            ]));
${indent}            foreach ($records as $record) {
${indent}                $writer->addRow(\\OpenSpout\\Common\\Entity\\Row::fromValues([
${indent}                    ${fields.join(`,\n${indent}                    `)}
${indent}                ]));
${indent}            }
${indent}            $writer->close();
${indent}        }, '${actionName}.xlsx');
${indent}    }),`;
        
        changesMade = true;
        return spoutBlock.replace(/,\s*$/, (fullMatch.endsWith(';') ? ';' : ',')); 
    });
    
    if (changesMade) {
        fs.writeFileSync(file, updatedContent);
        modifiedCount++;
        console.log(`Updated: ${file}`);
    }
}
console.log(`Total files modified: ${modifiedCount}`);
