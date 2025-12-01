
  // Import the functions you need from the SDKs you need
  import { initializeApp } from "https://www.gstatic.com/firebasejs/12.6.0/firebase-app.js";
  import { getAnalytics } from "https://www.gstatic.com/firebasejs/12.6.0/firebase-analytics.js";
  // TODO: Add SDKs for Firebase products that you want to use
  // https://firebase.google.com/docs/web/setup#available-libraries

  // Your web app's Firebase configuration
  // For Firebase JS SDK v7.20.0 and later, measurementId is optional
  const firebaseConfig = {
    apiKey: "AIzaSyBJws3FwFv37N_mr39kSSSBsan6xJqeHiw",
    authDomain: "projeto-final-php-7351f.firebaseapp.com",
    projectId: "projeto-final-php-7351f",
    storageBucket: "projeto-final-php-7351f.firebasestorage.app",
    messagingSenderId: "827626117334",
    appId: "1:827626117334:web:c009f79a0b3b141d3c5177",
    measurementId: "G-R581WJKD67"
  };

  // Initialize Firebase
  const app = initializeApp(firebaseConfig);
  const analytics = getAnalytics(app);
