<x-field name="brand" :label="__('Brand')" :value="$item->brand" span="3" required list="car-brands" />
<x-field name="model" :label="__('Model')" :value="$item->model" span="3" required />
<x-field name="year_made" type="number" :label="__('Year of manufacture')" :value="$item->year_made" span="3" />
<x-field name="color" :label="__('Color')" :value="$item->color" span="3" />
<x-field name="plate_number" :label="__('State number')" :value="$item->plate_number" span="3" placeholder="01 A 123 BC" />
<x-field name="vin" :label="__('Body number (VIN)')" :value="$item->vin" span="3" />
<x-field name="engine_number" :label="__('Engine number')" :value="$item->engine_number" span="3" />
<x-field name="chassis_number" :label="__('Chassis number')" :value="$item->chassis_number" span="3" />
<x-field name="tech_passport" :label="__('Registration certificate')" :value="$item->tech_passport" span="3" />
<x-field name="body_type" :label="__('Body type')" :value="$item->body_type" span="3" />
<x-field name="mileage" :label="__('Mileage')" :value="$item->mileage" money :suffix="__('km')" span="3" />
<x-field name="engine_volume" type="number" :label="__('Engine capacity')" :value="$item->engine_volume" :suffix="__('l')" span="3" />
<x-select name="fuel_type" :label="__('Fuel')" :options="$fuelTypes" :value="$item->fuel_type" placeholder="—" span="3" />
<x-select name="transmission" :label="__('Transmission')" :options="$transmissions" :value="$item->transmission" placeholder="—" span="3" />
<x-field name="condition" :label="__('Technical condition')" :value="$item->condition" span="6" />
<datalist id="car-brands"><option value="Chevrolet"><option value="Daewoo"><option value="BYD"><option value="Kia"><option value="Hyundai"><option value="Toyota"><option value="Lada"><option value="Isuzu"><option value="MAN"></datalist>
