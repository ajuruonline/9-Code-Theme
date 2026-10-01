from pathlib import Path
css=(Path(__file__).resolve().parents[1]/'assets/css/main.css').read_text(errors='ignore')
for bad in ['}*{box-sizing:border-box}','}html{scroll-behavior:smooth}','}img{max-width:100%;height:auto}','}a{color:var(--ncu-accent)','}button,input,select,textarea{font:inherit}','}button{cursor:pointer}','}*:focus-visible{','@media(prefers-reduced-motion:reduce){*,*::before,*::after']:
    assert bad not in css, f'global Theme selector still leaks into plugin/Frontend Admin surfaces: {bad}'
print('PASS Conference Update CSS isolation contract')
for bad in ['}.nav-links{','}.search-form{','}.search-field{','}.comment-list{','}.widget{','}.wp-block-table table{','}.wp-block-details{','}.wp-block-separator{']:
    assert bad not in css, f'generic Theme class still leaks into embedded plugin/Frontend Admin content: {bad}'
