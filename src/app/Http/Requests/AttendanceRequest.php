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
    public function rules()
    {
        return [
            'clock_in_at'   => ['required', 'date'],
            'clock_out_at'  => ['required', 'date'],
            'breaks'        => ['nullable', 'array'],
            'breaks.*.start_at' => ['nullable', 'date'],
            'breaks.*.end_at'   => ['nullable', 'date'],
            'reason'          => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // ① 出勤/退勤の前後関係
            'clock_in_at.before'  => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out_at.after'  => '出勤時間もしくは退勤時間が不適切な値です',

            // ② 休憩開始の範囲外
            'breaks.*.start_at.after_or_equal' => '休憩時間が不適切な値です',
            'breaks.*.start_at.before_or_equal'=> '休憩時間が不適切な値です',

            // ③ 休憩終了の範囲外/開始より前
            'breaks.*.end_at.after'            => '休憩時間もしくは退勤時間が不適切な値です',
            'breaks.*.end_at.before_or_equal'  => '休憩時間もしくは退勤時間が不適切な値です',

            // ④ 備考
            'reason.required' => '備考を記入してください',

            // 型エラー時の補助
            'clock_in_at.date'                  => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out_at.date'                 => '出勤時間もしくは退勤時間が不適切な値です',
            'breaks.*.start_at.date'            => '休憩時間が不適切な値です',
            'breaks.*.end_at.date'              => '休憩時間もしくは退勤時間が不適切な値です',
        ];
    }

    public function attributes(): array
    {
        return [
            'clock_in_at'  => '出勤時間',
            'clock_out_at' => '退勤時間',
            'breaks.*.start_at' => '休憩開始時間',
            'breaks.*.end_at'   => '休憩終了時間',
            'reason'         => '備考',
        ];
    }

    /**
     * 相関チェック（配列のワイルドルールが効かない場合も確実に拾う）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // 値を Carbon へ（空なら null）
            $cin  = $this->toCarbon($this->input('clock_in_at'));
            $cout = $this->toCarbon($this->input('clock_out_at'));

            // ① 出勤 > 退勤（または退勤 < 出勤）
            if ($cin && $cout && $cin->gt($cout)) {
                $v->errors()->add('clock_out_at', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 休憩の検証
            $breaks = $this->input('breaks', []);
            foreach ((array)$breaks as $i => $br) {
                $s = $this->toCarbon($br['start_at'] ?? null);
                $e = $this->toCarbon($br['end_at']   ?? null);

                // ② 休憩開始が [出勤..退勤] の範囲外
                if ($s) {
                    if ($cin && $s->lt($cin)) {
                        $v->errors()->add("breaks.$i.start_at", '休憩時間が不適切な値です');
                    }
                    if ($cout && $s->gt($cout)) {
                        $v->errors()->add("breaks.$i.start_at", '休憩時間が不適切な値です');
                    }
                }

                // ③ 休憩終了が開始より前／退勤より後
                if ($e) {
                    if ($s && $e->lt($s)) {
                        $v->errors()->add("breaks.$i.end_at", '休憩時間もしくは退勤時間が不適切な値です');
                    }
                    if ($cout && $e->gt($cout)) {
                        $v->errors()->add("breaks.$i.end_at", '休憩時間もしくは退勤時間が不適切な値です');
                    }
                }
            }
        });
    }

    private function toCarbon($value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
