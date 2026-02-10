const fs = require('fs');

// FILE DEL MONDO 296
const FILE = 'database_mondo_296.json';

console.log(`Aggiornamento inattività per: ${FILE}`);

try {
    // Se il file non esiste (prima run), crealo vuoto
    if (!fs.existsSync(FILE)) {
        console.error(`❌ Errore: Il file ${FILE} non esiste! Ne creo uno vuoto.`);
        fs.writeFileSync(FILE, '[]');
    }

    const data = JSON.parse(fs.readFileSync(FILE, 'utf8'));
    const now = new Date();
    
    const finalData = data.map(h => {
        if (!h.p || h.p === 0) return h; 

        // Firma univoca: nome + punti
        const firmaAttuale = `${h.n}|${h.pt}`;
        
        // Prima volta
        if (!h.u) {
            h.u = now.toISOString();
            h.i = false;
            h.f = firmaAttuale;
            return h;
        }

        // Se cambiato -> Attivo
        if (h.f !== firmaAttuale) {
            h.u = now.toISOString();
            h.i = false;
            h.f = firmaAttuale;
        } else {
            // Se fermo da 24h -> Inattivo
            const orePassate = (now - new Date(h.u)) / (1000 * 60 * 60);
            if (orePassate >= 24) {
                h.i = true;
            }
        }
        return h;
    });

    fs.writeFileSync(FILE, JSON.stringify(finalData, null, 2));
    console.log(`✅ Storico 296 aggiornato.`);

} catch (e) {
    console.error("Errore JS:", e.message);
    process.exit(1);
}
