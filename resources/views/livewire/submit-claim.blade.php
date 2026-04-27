<div class="max-w-4xl mx-auto py-12 px-4">
    <form wire:submit.prevent="create">
        {{ $this->form }}

        <button type="submit" class="mt-4 bg-primary-600 text-white px-4 py-2 rounded-lg font-bold bg-blue-600">
            Pateikti duomenis
        </button>
    </form>

    @if (session()->has('message'))
        <div class="mt-4 p-4 bg-green-100 text-green-700 rounded-lg">
            {{ session('message') }}
        </div>
    @endif
    
    <x-filament-actions::modals />
</div>