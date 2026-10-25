@props(['name'])

@error($name)
    <p class="mt-1 text-xs italic text-red-600" role="alert" v-pre>{{ $message }}</p>
@enderror
