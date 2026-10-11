<?php

namespace Database\Factories;

/**
 * Realistic Uzbek names, addresses and identifiers for demo data and tests.
 */
class UzbekData
{
    private const MALE = ['Alisher', 'Bekzod', 'Jasur', 'Sardor', 'Rustam', 'Dilshod', 'Sherzod', 'Otabek', 'Javlon', 'Aziz', 'Asliddin', 'Bobur', 'Farrux', 'Ulug‘bek', 'Shoxrux', 'Akmal', 'Nodir', 'Sanjar', 'Botir', 'Anvar'];

    private const FEMALE = ['Dilnoza', 'Madina', 'Nilufar', 'Gulnora', 'Malika', 'Zarina', 'Shahnoza', 'Feruza', 'Kamola', 'Nodira', 'Sevara', 'Mohira'];

    private const LAST = ['Karimov', 'Rahimov', 'Tursunov', 'Saidov', 'Yusupov', 'Abdullayev', 'Ismoilov', 'Nazarov', 'Qodirov', 'Ergashev', 'Xolmatov', 'Mirzayev', 'Aliyev', 'Sultonov', 'Hasanov', 'Usmonov', 'Saydaliyev', 'Jo‘rayev'];

    private const FATHER = ['Bahodir', 'Rustam', 'Anvar', 'Shavkat', 'Erkin', 'Akbar', 'Ravshan', 'Alisher', 'Hamid', 'Olim'];

    private const STREETS = ['Amir Temur ko‘chasi', 'Mustaqillik shoh ko‘chasi', 'Navoiy ko‘chasi', 'Bobur ko‘chasi', 'Shota Rustaveli ko‘chasi', 'Chilonzor 9-kvartal', 'Yunusobod 4-mavze', 'Bunyodkor shoh ko‘chasi', 'Farg‘ona yo‘li', 'Qatortol ko‘chasi', 'Mirzo Ulug‘bek ko‘chasi', 'Ipak yo‘li ko‘chasi'];

    private const COMPANIES = ['Toshkent Qurilish Invest', 'Samarqand Agro Servis', 'Orient Textile', 'Navro‘z Savdo', 'Asia Logistics Group', 'Farg‘ona Meva Sabzavot', 'Buxoro Gilam', 'Ipak Yo‘li Trans', 'Zarafshon Metall', 'Sharq Farm', 'Yangi Avlod Mebel', 'Turon Energy'];

    public static function firstName(string $gender): string
    {
        return fake()->randomElement($gender === 'f' ? self::FEMALE : self::MALE);
    }

    public static function lastName(string $gender): string
    {
        $last = fake()->randomElement(self::LAST);

        return $gender === 'f' ? $last.'a' : $last;
    }

    public static function middleName(string $gender): string
    {
        $father = fake()->randomElement(self::FATHER);

        return $father.($gender === 'f' ? 'ovna' : 'ovich');
    }

    public static function company(): string
    {
        return '"'.fake()->randomElement(self::COMPANIES).'" '.fake()->randomElement(['MChJ', 'AJ', 'XK', 'QK']);
    }

    public static function address(): string
    {
        return fake()->randomElement(self::STREETS).', '.fake()->numberBetween(1, 160).'-uy';
    }

    public static function phone(): string
    {
        return '+998'.fake()->randomElement(['90', '91', '93', '94', '95', '97', '98', '99', '33', '88']).fake()->numerify('#######');
    }

    public static function cadastral(): string
    {
        return sprintf('%02d:%02d:%02d:%02d:%02d:%04d', 10, fake()->numberBetween(1, 12), fake()->numberBetween(1, 20), fake()->numberBetween(1, 9), fake()->numberBetween(1, 30), fake()->numberBetween(1, 9999));
    }

    public static function plate(): string
    {
        return sprintf('%02d %s %03d %s', fake()->randomElement([1, 10, 20, 25, 30, 40, 50, 60, 70, 75, 80, 85, 90, 95]), fake()->randomLetter(), fake()->numberBetween(1, 999), strtoupper(fake()->lexify('??')));
    }

    public static function vin(): string
    {
        return 'XWB'.strtoupper(fake()->bothify('#?##?#??#?######'));
    }
}
