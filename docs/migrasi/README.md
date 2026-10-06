# Berkas migrasi SIMAN-FKIP-2

- `pemetaan-pengguna.csv` — pemetaan **manual** `username,email`: setiap `username` pada sheet `users` SIMAN-2 harus dipetakan ke surel
  `@unsil.ac.id`. Username yang tidak dipetakan dilaporkan sebagai galat saat `siman2:impor`. Berkas ini boleh berisi data pribadi
  (surel) sehingga jangan dikomit setelah diisi; salin pengisiannya di luar repositori atau ke `storage/app/tmp`.
- Hasil uji migrasi (tanpa data pribadi) dicatat di `HASIL-UJI-MIGRASI.md` (langkah manusia F7.4).
