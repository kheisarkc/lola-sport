import { expect, Page, test } from '@playwright/test';
import { resolve } from 'node:path';

/*
 * Alur utama tiga aktor lewat browser sungguhan (HP dan desktop):
 * pelanggan pesan dan kirim bukti -> pegawai verifikasi, terima pelunasan, tandai hadir
 * -> pemilik melihat omzet yang sama dengan rinciannya.
 */

const password = 'DemoLola2026!';

/** Tanggal operasional N hari dari sekarang (WIB), format YYYY-MM-DD. */
function daysFromNow(days: number): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date(Date.now() + days * 86_400_000));
}

async function login(page: Page, email: string) {
  await page.goto('/masuk');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Kata sandi').fill(password);
  await page.getByRole('button', { name: 'Masuk' }).click();
}

async function expectNoHorizontalScroll(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

test('pelanggan, pegawai, dan pemilik menyelesaikan satu reservasi', async ({ page, browser }, testInfo) => {
  // Tiap proyek (hp/desktop) memakai tanggal berbeda supaya slotnya tidak bentrok.
  const date = daysFromNow(testInfo.project.name === 'hp' ? 5 : 6);
  const phone = '081234567890';

  // --- Pelanggan (tanpa akun) ---
  await page.goto(`/?tanggal=${date}`);
  await expectNoHorizontalScroll(page);
  // Langkah 1: pilih dua jam berurutan, lalu Lanjut.
  await page.getByRole('button', { name: 'Pilih Futsal Sintetis jam 19.00' }).click();
  await page.getByRole('button', { name: 'Pilih Futsal Sintetis jam 20.00' }).click();
  await expect(page.locator('#select-bar')).toContainText('2 jam · 19.00–21.00');
  await expect(page.locator('#select-bar')).toContainText('Rp260.000');
  await page.getByRole('link', { name: 'Lanjut' }).click();

  // Langkah 2: data pemesan + pilih DP.
  await expect(page.getByText('19.00–21.00 WIB')).toBeVisible();
  await page.getByLabel('Nama pemesan').fill('Pelanggan E2E');
  await page.getByLabel('Nomor WhatsApp').fill(phone);
  await expect(page.getByRole('radio', { name: /Bayar DP/ })).toBeChecked();
  await page.getByRole('button', { name: 'Pesan dan bayar' }).click();

  // Langkah 3: kirim bukti DP.
  await expect(page.getByText('Menunggu pembayaran')).toBeVisible();
  const code = (await page.locator('.code-box').innerText()).trim();
  expect(code).toMatch(/^LS-[A-Z0-9]{6}$/);

  // Pilihan DP dari langkah 2 dibawa ke sini, tidak ditanyakan lagi.
  await expect(page.locator('.pay-fixed')).toContainText('Bayar DP');
  await expect(page.locator('.pay-fixed')).toContainText('Rp130.000');
  await expect(page.getByRole('radio')).toHaveCount(0);
  await expect(page.getByText(/tunai/)).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Batalkan pesanan' })).toBeVisible();

  // Kembali ke jadwal lalu melanjutkan pesanan yang sama.
  await page.goto('/');
  await expect(page.locator('.my-bookings')).toContainText(code);
  await page.locator('.my-bookings').getByRole('link', { name: 'Lanjut bayar' }).click();
  await expect(page.locator('.code-box')).toHaveText(code);
  await page.getByLabel(/Bukti pembayaran/).setInputFiles({
    name: 'bukti.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 bukti demo'),
  });
  await page.getByRole('button', { name: 'Kirim bukti' }).click();
  await expect(page.getByText('Bukti terkirim. Pegawai akan memeriksanya.')).toBeVisible();
  await expect(page.getByText('Menunggu verifikasi').first()).toBeVisible();

  // Jadwal publik menampilkan slot terisi tanpa nama pemesan.
  await page.goto(`/?tanggal=${date}`);
  await expect(page.getByRole('button', { name: 'Pilih Futsal Sintetis jam 19.00' })).toHaveCount(0);
  await expect(page.getByText('Pelanggan E2E')).toHaveCount(0);

  // --- Pegawai ---
  const staffContext = await browser.newContext({ ...testInfo.project.use });
  const staff = await staffContext.newPage();
  await login(staff, 'pegawai@lola.test');
  await expect(staff).toHaveURL(/\/pegawai$/);
  // Menu bawah menunjukkan ada bukti yang menunggu.
  await expect(staff.locator('.bottom-nav')).toContainText('Cek bayar');
  await expect(staff.locator('.nav-count')).toBeVisible();

  // Kartu pesanan di "Hari ini" punya satu tombol utama: cek bukti.
  await staff.goto(`/pegawai?tanggal=${date}`);
  const card = staff.locator('.booking-card').filter({ hasText: code });
  await expect(staff.locator('.list-section.group-cek')).toContainText(code);
  await card.getByRole('link', { name: 'Cek bukti Rp130.000' }).click();
  await expectNoHorizontalScroll(staff);
  const proof = staff.locator('.proof-item').filter({ hasText: code });
  await proof.getByRole('button', { name: 'Uang sudah masuk' }).click();
  await expect(staff.getByText('Uang Rp130.000 dari Pelanggan E2E diterima.')).toBeVisible();

  // Setelah DP masuk, tombol utamanya berubah jadi terima tunai sisa.
  await staff.goto(`/pegawai?tanggal=${date}`);
  await expect(card.getByRole('button', { name: 'Pelanggan datang' })).toHaveCount(0);
  staff.once('dialog', (dialog) => dialog.accept());
  await card.getByRole('button', { name: 'Terima tunai Rp130.000' }).click();
  await expect(staff.getByText('Tunai Rp130.000 dicatat.')).toBeVisible();
  await expect(card.locator('.badge-money')).toHaveText('Lunas');
  await expectNoHorizontalScroll(staff);

  // Detail pesanan bisa dibuka dari kartu.
  await card.getByRole('link', { name: new RegExp(code) }).click();
  await expect(staff.locator('.booking-card-code')).toHaveText(code);
  await expectNoHorizontalScroll(staff);
  await staffContext.close();

  // --- Pelanggan cek ulang dengan kode + nomor WA dari browser lain ---
  const otherContext = await browser.newContext({ ...testInfo.project.use });
  const other = await otherContext.newPage();
  await other.goto(`/reservasi/${code}`);
  await expect(other).toHaveURL(/\/cek$/);
  await other.getByLabel('Kode booking').fill(code);
  await other.getByLabel('Nomor WhatsApp').fill(phone);
  await other.getByRole('button', { name: 'Lihat reservasi' }).click();
  await expect(other.getByText('Terkonfirmasi')).toBeVisible();
  await expect(other.getByText('Tidak ada pembayaran yang perlu dikirim.')).toBeVisible();
  await otherContext.close();

  // --- Pemilik ---
  const ownerContext = await browser.newContext({ ...testInfo.project.use });
  const owner = await ownerContext.newPage();
  await login(owner, 'pemilik@lola.test');
  await expect(owner).toHaveURL(/\/pemilik$/);
  // Ringkasan: angka utama + pembagian tunai/QRIS, tanpa tombol yang mengubah pesanan.
  await expect(owner.locator('.kpi').first()).toContainText('Uang masuk');
  await expect(owner.locator('.split-list').first()).toContainText('Tunai');
  await expect(owner.locator('.split-list').first()).toContainText('QRIS');
  await expect(owner.getByRole('button', { name: /Terima|Uang sudah masuk/ })).toHaveCount(0);
  await expectNoHorizontalScroll(owner);
  // Transaksi: DP lewat QRIS + pelunasan tunai, keduanya bisa ditelusuri ke kode.
  await owner.locator('.bottom-nav').getByRole('link', { name: 'Transaksi' }).click();
  const tx = owner.locator('.tx-card').filter({ hasText: code });
  await expect(tx).toHaveCount(2);
  await expect(tx.filter({ hasText: 'Pelunasan' })).toContainText('Tunai');
  await expect(tx.filter({ hasText: 'DP' })).toContainText('QRIS');
  await expectNoHorizontalScroll(owner);
  await ownerContext.close();
});

test('halaman pegawai dan pemilik tertutup tanpa login', async ({ page }) => {
  await page.goto('/pegawai');
  await expect(page).toHaveURL(/\/masuk$/);
  await page.goto('/pemilik');
  await expect(page).toHaveURL(/\/masuk$/);
});
