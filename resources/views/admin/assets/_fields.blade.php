<x-field name="name" :label="__('Name')" :value="$item->name" span="6" required />
<x-select name="category" :label="__('Category')" :options="$categories" :value="$item->category" placeholder="—" span="3" />
<x-field name="inventory_number" :label="__('Inventory number')" :value="$item->inventory_number" span="3" />
<x-field name="manufacturer" :label="__('Manufacturer')" :value="$item->manufacturer" span="3" />
<x-field name="model" :label="__('Model')" :value="$item->model" span="3" />
<x-field name="serial_number" :label="__('Serial number')" :value="$item->serial_number" span="3" />
<x-field name="year_made" type="number" :label="__('Year of manufacture')" :value="$item->year_made" span="3" />
<x-field name="quantity" :label="__('Quantity')" :value="$item->quantity" money span="3" required />
<x-select name="unit" :label="__('Unit')" :options="$units" :value="$item->unit" span="3" />
<x-field name="condition" :label="__('Technical condition')" :value="$item->condition" span="6" />
