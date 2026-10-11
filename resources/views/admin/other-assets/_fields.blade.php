<x-field name="name" :label="__('Name')" :value="$item->name" span="8" required />
<x-select name="category" :label="__('Category')" :options="$categories" :value="$item->category" placeholder="—" span="4" />
<x-textarea name="description" :label="__('Description')" :value="$item->description" rows="4" />
