{{-- The photo of the registration certificate is sent with this form (its field is in the vehicle's data). --}}
<form method="post" action="{{ route('admin.vehicles.tech-passport', $item->id) }}" enctype="multipart/form-data" id="tech-passport-form" hidden>
    @csrf
</form>
