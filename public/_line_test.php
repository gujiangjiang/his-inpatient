<?php header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>LT</title>
<script src="/js/core/format.js"></script>
<script src="/js/case_front_page/home_fields.js"></script>
<script src="/js/case_front_page/home.js"></script>
<script src="/js/case_front_page/home_print.js"></script>
<script src="/js/case_front_page/home_page1.js"></script>
<script src="/js/case_front_page/home_page2.js"></script>
</head><body>
<script>
CaseHome.admissionNo='A1';
CaseHome.data={hospital_name:'X院',pay_type:'',name:'顾江江',gender:'1',birth_date:'1993-09-22',age_y:'33',id_type:'0',id_card:'360103199309222216',birth_prov:'',birth_city:'',birth_county:'',native_prov:'',native_city:'',nation:'',occupation:'',marriage:'1',admit_route:'1',admit_time:'2026-10-07 08:00',admit_dept:'骨科',admit_ward:'11病区',stay_days:'22',discharge_diags:[],surgeries:[],icu:[{}],allergy:'1',case_quality:'1'};
for (var i=1;i<=24;i++) CaseHome.data['fee'+i]='0.00';
document.write('<style>'+CaseHome.printCss()+'</style>');
document.write(CaseHome.buildPage1());
</script>
</body></html>
