<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Leads;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MGMController extends Controller
{
    /**
     * Get available services list (public)
     */
    public function layanan()
    {
        try {
            $services = [
                [
                    'id' => 1,
                    'name' => 'Perawat Lansia',
                    'description' => 'Perawatan profesional untuk lansia dengan kebutuhan kesehatan khusus',
                    'icon' => 'elderly-care',
                    'features' => [
                        'Perawatan kesehatan harian',
                        'Bantuan mobilitas',
                        'Pendampingan aktivitas',
                        'Monitoring kesehatan'
                    ]
                ],
                [
                    'id' => 2,
                    'name' => 'Perawat Medis',
                    'description' => 'Perawat terlatih untuk kebutuhan medis di rumah',
                    'icon' => 'medical-care',
                    'features' => [
                        'Perawatan luka',
                        'Injeksi & obat-obatan',
                        'Terapi fisik',
                        'Monitoring vital signs'
                    ]
                ],
                [
                    'id' => 3,
                    'name' => 'Caregiver',
                    'description' => 'Pendamping untuk aktivitas sehari-hari',
                    'icon' => 'caregiver',
                    'features' => [
                        'Bantuan aktivitas harian',
                        'Memasak & menyiapkan makanan',
                        'Menemani & komunikasi',
                        'Bantuan kebersihan'
                    ]
                ],
                [
                    'id' => 4,
                    'name' => 'Perawat Pasca Operasi',
                    'description' => 'Perawatan khusus pasca operasi di rumah',
                    'icon' => 'post-surgery',
                    'features' => [
                        'Perawatan luka operasi',
                        'Monitoring pemulihan',
                        'Bantuan mobilitas terbatas',
                        'Administrasi obat'
                    ]
                ],
                [
                    'id' => 5,
                    'name' => 'Perawat Stroke',
                    'description' => 'Perawatan khusus untuk pasien stroke',
                    'icon' => 'stroke-care',
                    'features' => [
                        'Terapi rehabilitasi',
                        'Bantuan komunikasi',
                        'Latihan motorik',
                        'Monitoring kondisi'
                    ]
                ],
                [
                    'id' => 6,
                    'name' => 'Baby Sitter Medis',
                    'description' => 'Pengasuh anak dengan pengetahuan medis',
                    'icon' => 'baby-care',
                    'features' => [
                        'Perawatan bayi & balita',
                        'Monitoring tumbuh kembang',
                        'Pertolongan pertama',
                        'Stimulasi anak'
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * About company information (public)
     */
    public function about()
    {
        try {
            $about = [
                'company_name' => 'Mikala Graha Mandiri',
                'tagline' => 'Solusi Perawatan Kesehatan Profesional di Rumah',
                'description' => 'Mikala Graha Mandiri adalah penyedia layanan home care terpercaya yang menghubungkan klien dengan tenaga perawat dan caregiver profesional. Kami berkomitmen memberikan pelayanan kesehatan berkualitas tinggi dengan sentuhan manusiawi.',
                'established_year' => '2020',
                'vision' => 'Menjadi penyedia layanan home care terdepan di Indonesia yang memberikan pelayanan kesehatan berkualitas dan terpercaya.',
                'mission' => [
                    'Menyediakan tenaga perawat dan caregiver profesional yang terlatih',
                    'Memberikan pelayanan kesehatan yang berkualitas dan terjangkau',
                    'Membangun ekosistem kesehatan yang berkelanjutan',
                    'Meningkatkan kualitas hidup pasien dan keluarga'
                ],
                'values' => [
                    'Profesionalisme',
                    'Integritas',
                    'Empati',
                    'Inovasi',
                    'Keberlanjutan'
                ],
                'services_count' => 6,
                'mitra_count' => '100+',
                'clients_served' => '500+',
                'contact' => [
                    'phone' => '021-1234567',
                    'whatsapp' => '08123456789',
                    'email' => 'info@mikalagrahamandiri.com',
                    'address' => 'Jakarta, Indonesia'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $about
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve about information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit contact form / leads (public, no auth required)
     */
    public function submitLeads(Request $request)
    {
        try {
            $request->validate([
                'nama' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'kota' => 'nullable|string|max:100',
                'tipe_layanan' => 'nullable|string|max:100',
                'pesan' => 'required|string|max:1000',
            ]);

            DB::beginTransaction();

            // Create lead
            $lead = Leads::create([
                'nama' => $request->nama,
                'email' => $request->email,
                'phone' => $request->phone,
                'kota' => $request->kota,
                'source' => 'website',
                'tipe_layanan' => $request->tipe_layanan,
                'pesan' => $request->pesan,
                'status' => 'new',
            ]);

            // Notify marketing team
            $marketingUsers = \App\Models\User::whereIn('role', ['marketing', 'customer_care', 'manajemen'])->get();
            foreach ($marketingUsers as $user) {
                Notifikasi::create([
                    'user_id' => $user->id,
                    'title' => 'New Lead from Website',
                    'message' => "New lead from {$request->nama} - {$request->email}",
                    'type' => 'lead',
                    'related_type' => 'App\Models\Leads',
                    'related_id' => $lead->id,
                    'is_read' => false,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your interest! Our team will contact you soon.',
                'data' => [
                    'reference_number' => 'LEAD-' . str_pad($lead->id, 6, '0', STR_PAD_LEFT)
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
                'message' => 'Failed to submit contact form',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
