{{--
    Pilihan kategori (maks. 2 tingkat) yang bisa dicari. Subkategori tampil
    "Induk › Anak" supaya mudah dicari dari nama induknya.
    <x-form.category-select name="category_id" :categories="$categories" :selected="$product->category_id" placeholder="Tanpa kategori" />
    $categories: koleksi Category (induk & anak), urutan dipertahankan.
--}}
@props(['categories' => [], 'name' => 'category_id'])
@php
    $byId = collect($categories)->keyBy('id');
    $options = collect($categories)->mapWithKeys(function ($category) use ($byId) {
        $parent = $category->parent_id ? ($category->relationLoaded('parent') ? $category->parent : $byId->get($category->parent_id)) : null;

        return [$category->id => $parent ? $parent->name.' › '.$category->name : $category->name];
    })->all();
@endphp
<x-form.select :name="$name" :options="$options" :search="true" {{ $attributes }} />
