import nodemailer from 'nodemailer';

const chunks = [];
for await (const chunk of process.stdin) chunks.push(chunk);
const message = JSON.parse(Buffer.concat(chunks).toString('utf8'));

const host = process.env.MFA_SMTP_HOST;
const port = Number(process.env.MFA_SMTP_PORT || 587);
const user = process.env.MFA_SMTP_USER;
const password = process.env.MFA_SMTP_PASSWORD;
const from = process.env.MFA_MAIL_FROM || user;

if (!host || !user || !password || !from) {
  throw new Error('MFA SMTP configuration is incomplete.');
}

const transporter = nodemailer.createTransport({
  host,
  port,
  secure: port === 465,
  auth: { user, pass: password },
});

await transporter.sendMail({
  from,
  to: message.to,
  subject: message.subject,
  text: message.text,
  html: message.html,
});

process.stdout.write('sent');
