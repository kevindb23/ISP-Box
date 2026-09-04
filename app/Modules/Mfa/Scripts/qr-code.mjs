import QRCode from 'qrcode';

const chunks = [];
for await (const chunk of process.stdin) chunks.push(chunk);
const payload = Buffer.concat(chunks).toString('utf8').trim();

if (!payload) {
  throw new Error('An otpauth URI is required.');
}

process.stdout.write(await QRCode.toDataURL(payload, {
  errorCorrectionLevel: 'M',
  margin: 1,
  width: 220,
  color: { dark: '#101828', light: '#ffffff' },
}));
