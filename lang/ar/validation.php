<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */
    'accepted' => 'يجب قبول حقل :attribute.',
    'accepted_if' => 'يجب قبول حقل :attribute عندما يكون :other بقيمة :value.',
    'active_url' => 'يجب أن يكون حقل :attribute رابط URL صالحاً.',
    'after' => 'يجب أن يكون حقل :attribute تاريخاً بعد :date.',
    'after_or_equal' => 'يجب أن يكون حقل :attribute تاريخاً بعد أو يساوي :date.',
    'alpha' => 'يجب أن يحتوي حقل :attribute على حروف فقط.',
    'alpha_dash' => 'يجب أن يحتوي حقل :attribute على حروف، أرقام، شرطات، وشرطات سفلية فقط.',
    'alpha_num' => 'يجب أن يحتوي حقل :attribute على حروف وأرقام فقط.',
    'any_of' => 'حقل :attribute غير صالح.',
    'array' => 'يجب أن يكون حقل :attribute مصفوفة.',
    'ascii' => 'يجب أن يحتوي حقل :attribute على رموز وأحرف ASCII أحادية البايت فقط.',

    'before' => 'يجب أن يكون حقل :attribute تاريخاً قبل :date.',
    'before_or_equal' => 'يجب أن يكون حقل :attribute تاريخاً قبل أو يساوي :date.',

    'between' => [
        'array' => 'يجب أن يحتوي حقل :attribute على عدد عناصر بين :min و :max.',
        'file' => 'يجب أن يكون حجم ملف :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'string' => 'يجب أن يكون طول :attribute بين :min و :max حروف.',
    ],

    'boolean' => 'يجب أن تكون قيمة حقل :attribute صحيحة أو خاطئة.',
    'can' => 'حقل :attribute يحتوي على قيمة غير مصرح بها.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'contains' => 'حقل :attribute يفتقد قيمة مطلوبة.',
    'current_password' => 'كلمة المرور الحالية غير صحيحة.',

    'date' => 'يجب أن يكون حقل :attribute تاريخاً صالحاً.',
    'date_equals' => 'يجب أن يكون :attribute تاريخاً يساوي :date.',
    'date_format' => 'يجب أن يطابق حقل :attribute التنسيق :format.',

    'decimal' => 'يجب أن يحتوي :attribute على :decimal منازل عشرية.',
    'declined' => 'يجب رفض حقل :attribute.',
    'declined_if' => 'يجب رفض حقل :attribute عندما يكون :other بقيمة :value.',
    'different' => 'يجب أن يكون حقل :attribute مختلفاً عن :other.',

    'digits' => 'يجب أن يحتوي حقل :attribute على :digits أرقام.',
    'digits_between' => 'يجب أن يحتوي حقل :attribute على أرقام بين :min و :max.',
    'dimensions' => 'أبعاد الصورة في :attribute غير صالحة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكررة.',

    'doesnt_contain' => 'يجب ألا يحتوي حقل :attribute على أي من القيم التالية: :values.',
    'doesnt_end_with' => 'يجب ألا ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ حقل :attribute بأحد القيم التالية: :values.',

    'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صالحًا.',
    'ends_with' => 'يجب أن ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'enum' => 'القيمة المحددة في :attribute غير صالحة.',
    'exists' => 'القيمة المحددة في :attribute غير صالحة.',
    'extensions' => 'يجب أن يكون :attribute بامتداد من: :values.',

    'file' => 'يجب أن يكون :attribute ملفاً.',
    'filled' => 'يجب أن يحتوي حقل :attribute على قيمة.',

    'gt' => [
        'array' => 'يجب أن يحتوي :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value.',
        'string' => 'يجب أن يكون طول :attribute أكبر من :value حروف.',
    ],

    'gte' => [
        'array' => 'يجب أن يحتوي :attribute على :value عنصر أو أكثر.',
        'file' => 'يجب ألا يقل حجم :attribute عن :value كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :value.',
        'string' => 'يجب ألا يقل طول :attribute عن :value حروف.',
    ],

    'hex_color' => 'يجب أن يكون :attribute لوناً سداسياً صحيحاً.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => 'القيمة المختارة في :attribute غير صالحة.',
    'in_array' => 'يجب أن يكون :attribute موجوداً في :other.',
    'in_array_keys' => 'يجب أن يحتوي :attribute على أحد المفاتيح التالية: :values.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'ip' => 'يجب أن يكون :attribute عنوان IP صالحاً.',
    'ipv4' => 'يجب أن يكون :attribute عنوان IPv4 صالحاً.',
    'ipv6' => 'يجب أن يكون :attribute عنوان IPv6 صالحاً.',
    'json' => 'يجب أن يكون :attribute نص JSON صالحاً.',
    'list' => 'يجب أن يكون :attribute قائمة.',
    'lowercase' => 'يجب أن يكون :attribute بحروف صغيرة.',

    'lt' => [
        'array' => 'يجب أن يحتوي :attribute على أقل من :value عنصر.',
        'file' => 'يجب أن يكون :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من :value.',
        'string' => 'يجب أن يكون طول :attribute أقل من :value حروف.',
    ],

    'lte' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون :attribute أقل من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من أو تساوي :value.',
        'string' => 'يجب أن يكون طول :attribute أقل من أو يساوي :value حروف.',
    ],

    'mac_address' => 'يجب أن يكون :attribute عنوان MAC صالحاً.',

    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يزيد حجم :attribute عن :max كيلوبايت.',
        'numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.',
        'string' => 'يجب ألا يزيد طول :attribute عن :max حروف.',
    ],

    'max_digits' => 'يجب ألا يحتوي :attribute على أكثر من :max رقم.',
    'mimes' => 'يجب أن يكون :attribute ملفًا من النوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفًا من النوع: :values.',

    'min' => [
        'array' => 'يجب أن يحتوي :attribute على الأقل :min عنصر.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل طول :attribute عن :min حروف.',
    ],

    'min_digits' => 'يجب أن يحتوي :attribute على الأقل :min رقم.',

    'missing' => 'يجب أن يكون :attribute مفقوداً.',
    'missing_if' => 'يجب أن يكون :attribute مفقوداً عندما يكون :other بقيمة :value.',
    'missing_unless' => 'يجب أن يكون :attribute مفقوداً إلا إذا كان :other بقيمة :value.',
    'missing_with' => 'يجب أن يكون :attribute مفقوداً عند وجود :values.',
    'missing_with_all' => 'يجب أن يكون :attribute مفقوداً عند وجود :values.',

    'multiple_of' => 'يجب أن تكون قيمة :attribute مضاعفاً لـ :value.',
    'not_in' => 'القيمة المختارة في :attribute غير صالحة.',
    'not_regex' => 'تنسيق :attribute غير صالح.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',

    'password' => [
        'letters' => 'يجب أن تحتوي كلمة المرور على حرف واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي كلمة المرور على حرف كبير وصغير على الأقل.',
        'numbers' => 'يجب أن تحتوي كلمة المرور على رقم واحد على الأقل.',
        'symbols' => 'يجب أن تحتوي كلمة المرور على رمز واحد على الأقل.',
        'uncompromised' => 'تم العثور على :attribute في تسريب بيانات. الرجاء اختيار كلمة مرور أخرى.',
    ],

    'present' => 'يجب أن يكون :attribute موجوداً.',
    'present_if' => 'يجب أن يكون :attribute موجوداً عندما يكون :other بقيمة :value.',
    'present_unless' => 'يجب أن يكون :attribute موجوداً إلا إذا كان :other بقيمة :value.',
    'present_with' => 'يجب أن يكون :attribute موجوداً عند وجود :values.',
    'present_with_all' => 'يجب أن يكون :attribute موجوداً عند وجود :values.',

    'prohibited' => 'حقل :attribute محظور.',
    'prohibited_if' => 'حقل :attribute محظور عندما يكون :other بقيمة :value.',
    'prohibited_unless' => 'حقل :attribute محظور إلا إذا كان :other ضمن :values.',
    'prohibited_if_accepted' => 'حقل :attribute محظور عند قبول :other.',
    'prohibited_if_declined' => 'حقل :attribute محظور عند رفض :other.',

    'prohibits' => 'حقل :attribute يمنع :other من الوجود.',

    'regex' => 'تنسيق :attribute غير صالح.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي :attribute على المفاتيح التالية: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other بقيمة :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'حقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'حقل :attribute مطلوب ما لم يكن :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود أي من :values.',

    'same' => 'يجب أن يتطابق :attribute مع :other.',

    'size' => [
        'array' => 'يجب أن يحتوي :attribute على :size عنصر.',
        'file' => 'يجب أن يكون حجم :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute :size.',
        'string' => 'يجب أن يكون طول :attribute :size حرفاً.',
    ],

    'starts_with' => 'يجب أن يبدأ :attribute بأحد القيم التالية: :values.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'timezone' => 'يجب أن يكون :attribute منطقة زمنية صحيحة.',
    'unique' => 'قيمة :attribute مستخدمة من قبل.',
    'uploaded' => 'فشل رفع :attribute.',
    'uppercase' => 'يجب أن يكون :attribute بحروف كبيرة.',
    'url' => 'يجب أن يكون :attribute رابط URL صالحاً.',
    'ulid' => 'يجب أن يكون :attribute ULID صالحاً.',
    'uuid' => 'يجب أن يكون :attribute UUID صالحاً.',


    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        // users
        'username' => [
            'unique' => 'اسم المستخدم مستخدم بالفعل.',
        ],

        // general
        'required' => 'هذا الحقل مطلوب.',
        'unique' => 'هذه القيمة مستخدمة بالفعل.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
