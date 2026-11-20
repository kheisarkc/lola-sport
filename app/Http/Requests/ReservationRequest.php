<?php

namespace App\Http\Requests;

use App\Support\OperatingHours;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Isian reservasi yang sama untuk pelanggan (UC-02) dan pegawai (UC-07).
 */
class ReservationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => PhoneNumber::normalize($this->input('customer_phone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'hour' => ['required', 'integer', Rule::in(OperatingHours::startHours())],
            'duration' => ['required', 'integer', 'min:1', 'max:18'],
            // Pilihan bayar pelanggan di web; pegawai tidak mengirimnya.
            'pembayaran' => ['nullable', Rule::in(['dp', 'lunas'])],
            'customer_name' => ['required', 'string', 'min:2', 'max:80'],
            'customer_phone' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (! PhoneNumber::isValid((string) $value)) {
                    $fail('Nomor WhatsApp harus diawali 08 dan berisi 10-13 angka.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'court_id' => 'lapangan',
            'date' => 'tanggal',
            'hour' => 'jam mulai',
            'duration' => 'durasi',
            'pembayaran' => 'pilihan pembayaran',
            'customer_name' => 'nama',
            'customer_phone' => 'nomor WhatsApp',
        ];
    }

    /**
     * @return array{court_id: int, date: string, hour: int, duration: int, customer_name: string, customer_phone: string}
     */
    public function reservation(): array
    {
        return [
            'court_id' => $this->integer('court_id'),
            'date' => (string) $this->input('date'),
            'hour' => $this->integer('hour'),
            'duration' => $this->integer('duration'),
            'customer_name' => (string) $this->input('customer_name'),
            'customer_phone' => (string) $this->input('customer_phone'),
        ];
    }
}
