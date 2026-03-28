<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid slice" class="farm-svg">
  <!-- Sky -->
  <rect width="1000" height="600" fill="#f0f9ff" />
  
  <!-- Sun -->
  <circle cx="950" cy="50" r="40" fill="#fbbf24" class="sun-glow" />
  
  <!-- Clouds -->
  <g class="cloud cloud-1">
    <circle cx="100" cy="80" r="20" fill="white" fill-opacity="0.8" />
    <circle cx="130" cy="80" r="30" fill="white" fill-opacity="0.8" />
    <circle cx="160" cy="80" r="20" fill="white" fill-opacity="0.8" />
  </g>
  <g class="cloud cloud-2">
    <circle cx="600" cy="50" r="25" fill="white" fill-opacity="0.6" />
    <circle cx="635" cy="50" r="35" fill="white" fill-opacity="0.6" />
    <circle cx="670" cy="50" r="25" fill="white" fill-opacity="0.6" />
  </g>
  
  <!-- Hills -->
  <path d="M-100 600 Q 200 450 500 600" fill="#bef264" />
  <path d="M300 600 Q 600 400 1100 600" fill="#84cc16" />
  <path d="M-200 600 Q 400 520 1200 600" fill="#4d7c0f" />
  
  <!-- Wind Turbine -->
  <g transform="translate(900, 350)">
    <rect x="-2" y="0" width="4" height="150" fill="#94a3b8" />
    <g class="turbine-blades">
        <path d="M0,0 L0,-40 L5,-5 Z" fill="#cbd5e1" />
        <path d="M0,0 L35,20 L5,5 Z" fill="#cbd5e1" />
        <path d="M0,0 L-35,20 L-5,5 Z" fill="#cbd5e1" />
    </g>
  </g>

  <style>
    .turbine-blades {
        animation: rotate-turbine 5s linear infinite;
        transform-origin: 0 0;
    }
    @keyframes rotate-turbine { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
  </style>

</svg>
