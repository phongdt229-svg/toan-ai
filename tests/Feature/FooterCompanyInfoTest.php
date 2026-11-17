<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Khối giới thiệu đơn vị chủ quản ở chân trang (NĐ 52/2013) — phải có ở cả footer chung lẫn trang chủ V6. */
class FooterCompanyInfoTest extends TestCase
{
    use RefreshDatabase;

    private function configureCompany(): void
    {
        config([
            'site.company' => 'Công ty TNHH Fame Media',
            'site.company_tax_code' => '0316378944',
            'site.company_license_date' => '13/07/2020',
            'site.company_license_place' => 'Sở Kế hoạch và Đầu tư TP.HCM',
            'site.address' => '38/3/1 Trần Văn Mười, xã Bà Điểm, TPHCM',
            'site.office_address' => 'Tây Nguyên: 42/10 Nguyễn Hữu Thấu, P. Buôn Ma Thuột, Đắk Lắk',
        ]);
    }

    public function test_public_footer_shows_company_registration(): void
    {
        $this->configureCompany();

        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('data-company-info', false)
            ->assertSee('Công ty TNHH Fame Media')
            ->assertSee('Mã số doanh nghiệp: 0316378944, cấp lần đầu ngày 13/07/2020 tại Sở Kế hoạch và Đầu tư TP.HCM')
            ->assertSee('Trụ sở:')
            ->assertSee('Văn phòng Tây Nguyên: 42/10 Nguyễn Hữu Thấu');
    }

    public function test_landing_page_footer_shows_company_registration(): void
    {
        $this->configureCompany();

        $this->get('/')->assertOk()
            ->assertSee('data-company-info', false)
            ->assertSee('Mã số doanh nghiệp: 0316378944')
            ->assertSee('Trụ sở: 38/3/1 Trần Văn Mười, xã Bà Điểm, TPHCM')
            ->assertSee('Văn phòng Tây Nguyên:');
    }

    public function test_organization_schema_declares_the_tax_id(): void
    {
        $this->configureCompany();

        $this->get('/')->assertOk()->assertSee('"taxID":"0316378944"', false);
    }

    public function test_block_is_hidden_without_a_tax_code(): void
    {
        $this->configureCompany();
        config(['site.company_tax_code' => '']);

        $this->get(route('legal.terms'))->assertOk()->assertDontSee('data-company-info', false);
        $this->get('/')->assertOk()->assertDontSee('data-company-info', false);
    }
}
