<x-mail::message>
# 👋 Welcome {{ $user->name }}

Thank you for joining **arcades** — we’re excited to have you with us 🎉

<x-mail::panel>
🔐 Your verification code:<br>
<h2 style="text-align:center; color:#2e7d32">{{ $code }}</h2>
</x-mail::panel>

We're here to support you every step of the way 🧑‍⚕️
Feel free to reach out if you have any questions.

<x-mail::button :url="url('/')">
Visit Our Application
</x-mail::button>

Warm regards,
**The arcades Team**
</x-mail::message>
