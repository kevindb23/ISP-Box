import nodemailer from 'nodemailer';

const chunks = [];
for await (const chunk of process.stdin) chunks.push(chunk);
const message = JSON.parse(Buffer.concat(chunks).toString('utf8'));

const host = process.env.EMAIL_SMTP_HOST;
const port = Number(process.env.EMAIL_SMTP_PORT || 587);
const user = process.env.EMAIL_SMTP_USER;
const password = process.env.EMAIL_SMTP_PASSWORD;

if (!host || !user || !password || !process.env.EMAIL_MAIL_FROM) {
  throw new Error('SMTP configuration is incomplete.');
}

const transporter = nodemailer.createTransport({
  host,
  port,
  secure: process.env.EMAIL_SMTP_ENCRYPTION === 'SSL' || port === 465,
  requireTLS: process.env.EMAIL_SMTP_ENCRYPTION === 'TLS',
  auth: { user, pass: password },
});

await transporter.sendMail({
  from: { name: message.fromName, address: message.from },
  to: message.to,
  subject: message.subject,
  text: message.text,
  html: message.html,
});

process.stdout.write('sent');
