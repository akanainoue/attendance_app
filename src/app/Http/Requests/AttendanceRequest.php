<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;


class AttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    //
    
    public function rules()
    {
        return [
            'clock_in_at'   => ['required', 'date_format:H:i'],
            'clock_out_at'  => ['required', 'date_format:H:i'],
            'breaks'        => ['nullable', 'array'],
            'breaks.*.start_at' => ['nullable', 'date_format:H:i'],
            'breaks.*.end_at'   => ['nullable', 'date_format:H:i'],
            'reason'        => ['required', 'string'], 
        ];
    }

    public function messages(): array
    {
        // 個別フィールド用の前後関係メッセージは出さない
        return [
            'clock_in_at.date_format'  => '出勤時刻の形式が不正です（例: 09:00）',
            'clock_out_at.date_format' => '退勤時刻の形式が不正です（例: 18:00）',
            'breaks.*.start_at.date_format' => '休憩時間の形式が不正です（例: 12:30）',
            'breaks.*.end_at.date_format'   => '休憩時間の形式が不正です（例: 13:00）',
            'reason.required' => '備考を記入してください',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $v): void
    {
        $v->after(function (\Illuminate\Validation\Validator $v) {
            $cin  = $this->toCarbon($this->input('clock_in_at'));
            $cout = $this->toCarbon($this->input('clock_out_at'));

            // 出退勤の前後関係（等号を許可したいなら gt → gte にしない。許可するなら "gt" チェックを "lt" の否定に変更）
            if ($cin && $cout && $cin->gt($cout)) {
                $v->errors()->add('work_time', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 休憩：どれか1つでもおかしければ break_time に1件だけ追加
            $breakErr = false;
            $breaks = (array) $this->input('breaks', []);
            foreach ($breaks as $br) {
                $s = $this->toCarbon($br['start_at'] ?? null);
                $e = $this->toCarbon($br['end_at']   ?? null);

                if ($s) {
                    if ($cin && $s->lt($cin))  $breakErr = true;        // 出勤より前
                    if ($cout && $s->gt($cout)) $breakErr = true;       // 退勤より後
                }
                if ($e) {
                    if ($s && $e->lt($s))      $breakErr = true;        // 開始より前で終了
                    if ($cout && $e->gt($cout)) $breakErr = true;       // 退勤より後に終了
                }
            }
            if ($breakErr) {
                $v->errors()->add('break_time', '休憩時間が不適切な値です');
            }
        });
    }

    private function toCarbon($v): ?\Illuminate\Support\Carbon
    {
        try { return $v ? \Illuminate\Support\Carbon::parse($v) : null; }
        catch (\Throwable $e) { return null; }
    }
}
