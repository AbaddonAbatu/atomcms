@props(['name', 'content' => null])

<div class="mt-3">
    <textarea name="content" id="editor">
        {{ $content ?? ''}}
    </textarea>
</div>

<x-core::form.wysiwyg-assets />
