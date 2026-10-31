{{-- Script của mẫu V6, giữ nguyên; chỉ giới hạn danh sách lớp theo config/learning.php. --}}
<script>
const GRADE_MIN={{ config('learning.grade_min') }},GRADE_MAX={{ config('learning.grade_max') }};
@verbatim
const grade=document.getElementById('grade');for(let i=GRADE_MIN;i<=GRADE_MAX;i++){const o=document.createElement('option');o.value=i;o.textContent='Lớp '+i;grade.appendChild(o)}
const pathGrade=document.getElementById('pathGrade');
const pathTopics={
1:[['Số và đếm','Nhận biết, đếm và so sánh số'],['Cộng và trừ','Luyện phép tính trong phạm vi đã học'],['Hình và đo lường','Nhận biết hình và độ dài']],
2:[['Số và phép tính','Cộng trừ có nhớ'],['Bài toán có lời văn','Đọc hiểu và chọn phép tính'],['Hình học','Nhận biết hình và đo lường']],
3:[['Nhân và chia','Luyện bảng nhân, bảng chia'],['Giải toán','Bài toán có lời văn'],['Đo lường','Thời gian và đơn vị đo']],
4:[['Số tự nhiên','Các phép tính với số tự nhiên'],['Phân số','Nhận biết và so sánh phân số'],['Hình học','Góc và các hình cơ bản']],
5:[['Số thập phân','Các phép tính với số thập phân'],['Tỉ số phần trăm','Bài toán ứng dụng'],['Hình học','Chu vi, diện tích và thể tích']],
6:[['Số nguyên','So sánh và các phép tính'],['Phân số','Phép tính và bài toán'],['Hình học','Hình phẳng và đo lường']],
7:[['Số hữu tỉ','Ôn các phép tính và quy tắc dấu'],['Biểu thức đại số','Luyện tính giá trị biểu thức'],['Hình học','Nhận biết, suy luận và giải bài tập']],
8:[['Biểu thức đại số','Các phép biến đổi cơ bản'],['Phương trình','Luyện giải phương trình bậc nhất'],['Hình học','Tứ giác và bài toán hình']],
9:[['Căn thức','Biến đổi biểu thức chứa căn'],['Phương trình','Giải phương trình và hệ phương trình'],['Hình học','Đường tròn và các bài toán']],
10:[['Mệnh đề và tập hợp','Nhận biết và vận dụng'],['Hàm số','Đồ thị và tính chất cơ bản'],['Vectơ','Các phép toán vectơ']],
11:[['Lượng giác','Công thức và phương trình'],['Dãy số','Cấp số cộng và cấp số nhân'],['Giới hạn','Luyện bài toán giới hạn']],
12:[['Đạo hàm','Ứng dụng khảo sát hàm số'],['Nguyên hàm và tích phân','Bài toán cơ bản'],['Xác suất','Luyện các bài toán xác suất']]
};
for(let i=GRADE_MIN;i<=GRADE_MAX;i++){const o=document.createElement('option');o.value=String(i);o.textContent='Lớp '+i;pathGrade.appendChild(o)}
function updatePath(){const g=pathGrade.value;document.getElementById('pathTitle').textContent='Các chủ đề tham khảo – Lớp '+g;document.getElementById('pathSubtitle').textContent='Ví dụ nội dung theo lớp, không phải đánh giá cá nhân.';pathTopics[g].forEach((topic,i)=>{document.getElementById('topic'+(i+1)+'Title').textContent=topic[0];document.getElementById('topic'+(i+1)+'Desc').textContent=topic[1]})}
pathGrade.value=String(Math.min(Math.max(7,GRADE_MIN),GRADE_MAX));updatePath();pathGrade.addEventListener('change',()=>{updatePath();grade.value=pathGrade.value});

const menu=document.getElementById('mobileMenu'),menuBtn=document.getElementById('menuBtn');menuBtn.addEventListener('click',()=>{const open=menu.classList.toggle('open');menuBtn.setAttribute('aria-expanded',String(open));menuBtn.setAttribute('aria-label',open?'Đóng menu':'Mở menu')});menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{menu.classList.remove('open');menuBtn.setAttribute('aria-expanded','false');menuBtn.setAttribute('aria-label','Mở menu')}));document.getElementById('entryForm').addEventListener('submit',()=>{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'parent_registration_started',selected_grade:grade.value,goal:document.getElementById('goal').value})});
@endverbatim
</script>
