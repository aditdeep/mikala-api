<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MGAController extends Controller
{
    /**
     * Get available training programs (public)
     */
    public function programPelatihan()
    {
        try {
            $programs = [
                [
                    'id' => 1,
                    'name' => 'Basic Caregiving',
                    'description' => 'Pelatihan dasar merawat pasien di rumah',
                    'duration' => '2 minggu',
                    'level' => 'Pemula',
                    'certification' => 'Sertifikat Basic Caregiving',
                    'topics' => [
                        'Dasar-dasar perawatan',
                        'Komunikasi dengan pasien',
                        'Kebersihan dan sanitasi',
                        'Pertolongan pertama',
                        'Etika profesi'
                    ],
                    'requirements' => [
                        'Minimal SMA/sederajat',
                        'Usia 18-45 tahun',
                        'Sehat jasmani dan rohani',
                        'Berkomitmen bekerja sebagai caregiver'
                    ]
                ],
                [
                    'id' => 2,
                    'name' => 'Medical Care',
                    'description' => 'Pelatihan perawatan medis di rumah',
                    'duration' => '4 minggu',
                    'level' => 'Menengah',
                    'certification' => 'Sertifikat Medical Home Care',
                    'topics' => [
                        'Perawatan luka',
                        'Injeksi dan pemberian obat',
                        'Monitoring vital signs',
                        'Pemasangan NGT & catheter',
                        'Manajemen pasien kronis'
                    ],
                    'requirements' => [
                        'Lulusan D3/S1 Keperawatan atau Kebidanan',
                        'Atau memiliki sertifikat Basic Caregiving',
                        'Usia 20-45 tahun',
                        'STR aktif (untuk tenaga kesehatan)'
                    ]
                ],
                [
                    'id' => 3,
                    'name' => 'Elderly Care Specialist',
                    'description' => 'Spesialisasi perawatan lansia',
                    'duration' => '3 minggu',
                    'level' => 'Menengah',
                    'certification' => 'Sertifikat Elderly Care Specialist',
                    'topics' => [
                        'Karakteristik lansia',
                        'Penyakit degeneratif',
                        'Komunikasi dengan lansia',
                        'Aktivitas dan terapi lansia',
                        'Palliative care'
                    ],
                    'requirements' => [
                        'Memiliki sertifikat Basic Caregiving',
                        'Pengalaman minimal 6 bulan',
                        'Sabar dan empati tinggi'
                    ]
                ],
                [
                    'id' => 4,
                    'name' => 'Stroke & Rehabilitation Care',
                    'description' => 'Perawatan dan rehabilitasi pasien stroke',
                    'duration' => '3 minggu',
                    'level' => 'Lanjutan',
                    'certification' => 'Sertifikat Stroke Care',
                    'topics' => [
                        'Patofisiologi stroke',
                        'Fase pemulihan stroke',
                        'Terapi rehabilitasi',
                        'Latihan motorik',
                        'Komunikasi dengan pasien stroke'
                    ],
                    'requirements' => [
                        'Tenaga kesehatan atau sertifikat Medical Care',
                        'Pengalaman merawat pasien'
                    ]
                ],
                [
                    'id' => 5,
                    'name' => 'Post Surgery Care',
                    'description' => 'Perawatan pasien pasca operasi',
                    'duration' => '2 minggu',
                    'level' => 'Lanjutan',
                    'certification' => 'Sertifikat Post Surgery Care',
                    'topics' => [
                        'Perawatan luka operasi',
                        'Pencegahan infeksi',
                        'Monitoring komplikasi',
                        'Nutrisi pasca operasi',
                        'Mobilisasi bertahap'
                    ],
                    'requirements' => [
                        'Tenaga kesehatan profesional',
                        'Sertifikat Medical Care'
                    ]
                ],
                [
                    'id' => 6,
                    'name' => 'Baby & Child Care',
                    'description' => 'Perawatan bayi dan anak',
                    'duration' => '2 minggu',
                    'level' => 'Pemula-Menengah',
                    'certification' => 'Sertifikat Baby & Child Care',
                    'topics' => [
                        'Tumbuh kembang anak',
                        'ASI dan MPASI',
                        'Stimulasi anak',
                        'Pertolongan pertama anak',
                        'Penyakit umum anak'
                    ],
                    'requirements' => [
                        'Minimal SMA/sederajat',
                        'Menyukai anak',
                        'Sabar dan telaten'
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $programs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve training programs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register for training program (public, no auth required)
     */
    public function daftarPelatihan(Request $request)
    {
        try {
            $request->validate([
                'nama_lengkap' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'required|string|max:20',
                'nik' => 'required|string|size:16|unique:mitra,nik',
                'tanggal_lahir' => 'required|date|before:today',
                'jenis_kelamin' => 'required|in:male,female',
                'alamat' => 'required|string|max:500',
                'kota' => 'required|string|max:100',
                'pendidikan_terakhir' => 'required|string|max:100',
                'program' => 'required|string|max:100',
                'preferred_schedule' => 'nullable|string|max:100',
                'pengalaman' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            // Create user account
            $user = User::create([
                'name' => $request->nama_lengkap,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make('password123'), // Default password, will be changed on first login
                'role' => 'mitra',
                'is_active' => false, // Inactive until approved
            ]);

            // Create mitra profile
            $mitra = Mitra::create([
                'user_id' => $user->id,
                'nik' => $request->nik,
                'nama_lengkap' => $request->nama_lengkap,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'alamat' => $request->alamat,
                'kota' => $request->kota,
                'pendidikan_terakhir' => $request->pendidikan_terakhir,
                'pengalaman' => $request->pengalaman,
                'status' => 'pending',
                'is_verified'=>DB::raw('false'),
                'training_status' => 'pending',
            ]);

            // Notify rekrutmen team
            $rekrutmenUsers = User::whereIn('role', ['rekrutmen', 'training_center', 'manajemen'])->get();
            foreach ($rekrutmenUsers as $admin) {
                Notifikasi::create([
                    'user_id' => $admin->id,
                    'title' => 'New Training Registration',
                    'message' => "New training registration from {$request->nama_lengkap} for program: {$request->program}",
                    'type' => 'recruitment',
                    'related_type' => 'App\Models\Mitra',
                    'related_id' => $mitra->id,
                    'is_read' => false,
                ]);
            }

            // Send welcome notification to new mitra
            Notifikasi::create([
                'user_id' => $user->id,
                'title' => 'Welcome to Mikala Academy',
                'message' => "Thank you for registering! Our team will review your application and contact you soon.",
                'type' => 'welcome',
                'is_read' => false,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please check your email for further instructions.',
                'data' => [
                    'reference_number' => 'MGA-' . str_pad($mitra->id, 6, '0', STR_PAD_LEFT),
                    'email' => $user->email,
                    'temporary_password' => 'password123',
                    'note' => 'Please change your password after first login'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to register for training',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
