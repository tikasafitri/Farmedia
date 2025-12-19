{{-- resources/views/components/application-logo.blade.php --}}
<img
    src="{{ asset('images/logo.png') }}"
    alt="Farmedia"
    {{ $attributes->merge([
        // default kecil, bisa dioverride dari luar
        'class' => 'rounded-full object-cover h-10 w-10',
    ]) }}
>
