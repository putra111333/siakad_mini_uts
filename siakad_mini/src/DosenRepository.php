<?php
// File: src/DosenRepository.php

class DosenRepository {
    private PDO $db;

    // Masukkan koneksi PDO database lewat constructor
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * LEVEL 2: Mengambil data dosen aktif dengan fitur lengkap
     * Search, Filter Program Studi, Sorting Kolom, dan Paginasi Data
     */
    public function getAllActive(string $search, string $studi, string $sortCol, string $sortDir, int $limit, int $offset): array {
        // Keamanan: Whitelist kolom sorting untuk mencegah SQL Injection via ORDER BY
        $allowedCols = ['nama', 'nidn', 'email', 'program_studi', 'status'];
        $sortCol = in_array($sortCol, $allowedCols, true) ? $sortCol : 'nama';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        // Query SQL dasar (Menghitung total mata kuliah yang diampu lewat LEFT JOIN)
        $sql = "SELECT d.*, COUNT(dm.matakuliah_id) as total_mk 
                FROM dosen d 
                LEFT JOIN dosen_matakuliah dm ON d.id = dm.dosen_id
                WHERE d.deleted_at IS NULL"; // Hanya ambil yang belum di-soft delete
        
        $params = [];

        // Fitur Pencarian Nama atau NIDN
        if ($search !== '') {
            $sql .= " AND (d.nama LIKE :search OR d.nidn LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        // Fitur Filter Program Studi
        if ($studi !== '') {
            $sql .= " AND d.program_studi = :studi";
            $params[':studi'] = $studi;
        }

        // Pengelompokan, Pengurutan, dan Batasan Paginasi
        $sql .= " GROUP BY d.id ORDER BY d.{$sortCol} {$sortDir} LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind parameter teks/string
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        // Bind parameter angka untuk LIMIT & OFFSET (Wajib explicitly agar tipe datanya INTEGER)
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Menghitung total data dosen aktif untuk keperluan pembuatan nomor halaman paginasi
     */
    public function countActive(string $search, string $studi): int {
        $sql = "SELECT COUNT(*) FROM dosen WHERE deleted_at IS NULL";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (nama LIKE :search OR nidn LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        if ($studi !== '') {
            $sql .= " AND program_studi = :studi";
            $params[':studi'] = $studi;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Mencari satu data dosen berdasarkan ID
     */
    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM dosen WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * LEVEL 2: Update Data Dosen Sekaligus Alokasi Mengajar (Many-to-Many)
     * Menggunakan DATABASE TRANSACTION (Atomisitas data aman dari kegagalan parsial)
     */
    public function update(int $id, array $data, array $matakuliahIds, string $semester): bool {
        try {
            // Mulai Transaksi Atomik
            $this->db->beginTransaction();

            // 1. Update data dasar dosen di tabel `dosen`
            $stmt = $this->db->prepare("UPDATE dosen SET nidn = :nidn, nama = :nama, email = :email, 
                                        program_studi = :program_studi, foto = :foto, status = :status WHERE id = :id");
            $stmt->execute([
                ':nidn'          => $data['nidn'],
                ':nama'          => $data['nama'],
                ':email'         => $data['email'],
                ':program_studi' => $data['program_studi'],
                ':foto'          => $data['foto'],
                ':status'        => $data['status'],
                ':id'            => $id
            ]);

            // 2. Bersihkan/Hapus alokasi mengajar yang lama di tabel pivot jembatan
            $stmtDel = $this->db->prepare("DELETE FROM dosen_matakuliah WHERE dosen_id = :dosen_id");
            $stmtDel->execute([':dosen_id' => $id]);

            // 3. Masukkan alokasi mengajar yang baru dipilih ke tabel pivot jembatan
            if (!empty($matakuliahIds)) {
                $stmtIns = $this->db->prepare("INSERT INTO dosen_matakuliah (dosen_id, matakuliah_id, semester) VALUES (:dosen_id, :mk_id, :semester)");
                foreach ($matakuliahIds as $mkId) {
                    $stmtIns->execute([
                        ':dosen_id'     => $id,
                        ':mk_id'        => (int)$mkId,
                        ':semester'     => $semester
                    ]);
                }
            }

            // Jika semua query sukses tanpa ada kendala, commit ke database secara permanen
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // Jika ada salah satu query yang gagal/error di tengah jalan, batalkan semuanya!
            $this->db->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * LEVEL 1: Soft Delete (Mengisi tanda waktu pada kolom deleted_at, data tidak benar-benar lenyap)
     */
    public function softDelete(int $id): bool {
        $stmt = $this->db->prepare("UPDATE dosen SET deleted_at = NOW() WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Mengambil ID mata kuliah apa saja yang saat ini sedang dicentang/diambil oleh si dosen
     */
    public function getDosenMatakuliahIds(int $dosenId): array {
        $stmt = $this->db->prepare("SELECT matakuliah_id FROM dosen_matakuliah WHERE dosen_id = :dosen_id");
        $stmt->execute([':dosen_id' => $dosenId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN); // Mengembalikan array satu dimensi berisi ID matakuliah
    }

    /**
     * LEVEL 2 (Lanjutan): Mengambil data dosen yang berada di Keranjang Sampah (Melihat data soft-delete)
     */
    public function getTrash(): array {
        $stmt = $this->db->query("SELECT * FROM dosen WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
        return $stmt->fetchAll();
    }

    /**
     * LEVEL 2 (Lanjutan): Memulihkan kembali data dosen dari keranjang sampah
     */
    public function restore(int $id): bool {
        $stmt = $this->db->prepare("UPDATE dosen SET deleted_at = NULL WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}