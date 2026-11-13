<?php

/**
 * Every state and union territory capital, plus major cities, for the city picker
 * (Onboarding step 3, Account → Preferences → City). ~120 cities rather than literally every
 * Indian town — a practical "all the cities" a picker like this needs, not an exhaustive census.
 *
 * Each row: [name_en, name_hi, state, latitude, longitude, is_popular]. Coordinates are
 * approximate city centres, accurate enough for "nearest city" geolocation matching.
 *
 * The original 6 demo cities (Lucknow, Delhi, Mumbai, Jaipur, Patna, Bhopal) keep their exact
 * name_en spelling — other seeders and tests look them up by that name.
 */
return [
    // Andhra Pradesh
    ['Amaravati', 'अमरावती', 'Andhra Pradesh', 16.5130, 80.5150, false],
    ['Visakhapatnam', 'विशाखापत्तनम', 'Andhra Pradesh', 17.6868, 83.2185, true],
    ['Vijayawada', 'विजयवाड़ा', 'Andhra Pradesh', 16.5062, 80.6480, false],
    ['Guntur', 'गुंटूर', 'Andhra Pradesh', 16.3067, 80.4365, false],
    ['Nellore', 'नेल्लोर', 'Andhra Pradesh', 14.4426, 79.9865, false],
    ['Tirupati', 'तिरुपति', 'Andhra Pradesh', 13.6288, 79.4192, false],
    ['Kurnool', 'कर्नूल', 'Andhra Pradesh', 15.8281, 78.0373, false],
    ['Rajahmundry', 'राजमहेंद्रवरम', 'Andhra Pradesh', 17.0005, 81.8040, false],

    // Arunachal Pradesh
    ['Itanagar', 'ईटानगर', 'Arunachal Pradesh', 27.0844, 93.6053, false],

    // Assam
    ['Guwahati', 'गुवाहाटी', 'Assam', 26.1445, 91.7362, true],
    ['Silchar', 'सिलचर', 'Assam', 24.8333, 92.7789, false],
    ['Jorhat', 'जोरहाट', 'Assam', 26.7509, 94.2037, false],

    // Bihar
    ['Patna', 'पटना', 'Bihar', 25.5941, 85.1376, true],
    ['Gaya', 'गया', 'Bihar', 24.7955, 85.0002, false],
    ['Bhagalpur', 'भागलपुर', 'Bihar', 25.2425, 86.9842, false],
    ['Muzaffarpur', 'मुज़फ़्फ़रपुर', 'Bihar', 26.1225, 85.3906, false],

    // Chhattisgarh
    ['Raipur', 'रायपुर', 'Chhattisgarh', 21.2514, 81.6296, false],
    ['Bhilai', 'भिलाई', 'Chhattisgarh', 21.1938, 81.3509, false],
    ['Bilaspur', 'बिलासपुर', 'Chhattisgarh', 22.0797, 82.1409, false],

    // Goa
    ['Panaji', 'पणजी', 'Goa', 15.4909, 73.8278, false],
    ['Margao', 'मडगांव', 'Goa', 15.2832, 73.9862, false],

    // Gujarat
    ['Gandhinagar', 'गांधीनगर', 'Gujarat', 23.2156, 72.6369, false],
    ['Ahmedabad', 'अहमदाबाद', 'Gujarat', 23.0225, 72.5714, true],
    ['Surat', 'सूरत', 'Gujarat', 21.1702, 72.8311, true],
    ['Vadodara', 'वडोदरा', 'Gujarat', 22.3072, 73.1812, false],
    ['Rajkot', 'राजकोट', 'Gujarat', 22.3039, 70.8022, false],
    ['Bhavnagar', 'भावनगर', 'Gujarat', 21.7645, 72.1519, false],
    ['Jamnagar', 'जामनगर', 'Gujarat', 22.4707, 70.0577, false],

    // Haryana
    ['Gurugram', 'गुरुग्राम', 'Haryana', 28.4595, 77.0266, false],
    ['Faridabad', 'फ़रीदाबाद', 'Haryana', 28.4089, 77.3178, false],
    ['Panipat', 'पानीपत', 'Haryana', 29.3909, 76.9635, false],
    ['Ambala', 'अंबाला', 'Haryana', 30.3782, 76.7767, false],

    // Himachal Pradesh
    ['Shimla', 'शिमला', 'Himachal Pradesh', 31.1048, 77.1734, false],
    ['Dharamshala', 'धर्मशाला', 'Himachal Pradesh', 32.2190, 76.3234, false],

    // Jharkhand
    ['Ranchi', 'रांची', 'Jharkhand', 23.3441, 85.3096, false],
    ['Jamshedpur', 'जमशेदपुर', 'Jharkhand', 22.8046, 86.2029, false],
    ['Dhanbad', 'धनबाद', 'Jharkhand', 23.7957, 86.4304, false],
    ['Bokaro', 'बोकारो', 'Jharkhand', 23.6693, 86.1511, false],

    // Karnataka
    ['Bengaluru', 'बेंगलुरु', 'Karnataka', 12.9716, 77.5946, true],
    ['Mysuru', 'मैसूरु', 'Karnataka', 12.2958, 76.6394, false],
    ['Hubballi-Dharwad', 'हुबली-धारवाड़', 'Karnataka', 15.3647, 75.1240, false],
    ['Mangaluru', 'मंगलुरु', 'Karnataka', 12.9141, 74.8560, false],
    ['Belagavi', 'बेलगावी', 'Karnataka', 15.8497, 74.4977, false],

    // Kerala
    ['Thiruvananthapuram', 'तिरुवनंतपुरम', 'Kerala', 8.5241, 76.9366, true],
    ['Kochi', 'कोच्चि', 'Kerala', 9.9312, 76.2673, true],
    ['Kozhikode', 'कोझिकोड', 'Kerala', 11.2588, 75.7804, false],
    ['Thrissur', 'त्रिशूर', 'Kerala', 10.5276, 76.2144, false],

    // Madhya Pradesh
    ['Bhopal', 'भोपाल', 'Madhya Pradesh', 23.2599, 77.4126, true],
    ['Indore', 'इंदौर', 'Madhya Pradesh', 22.7196, 75.8577, true],
    ['Jabalpur', 'जबलपुर', 'Madhya Pradesh', 23.1815, 79.9864, false],
    ['Gwalior', 'ग्वालियर', 'Madhya Pradesh', 26.2183, 78.1828, false],
    ['Ujjain', 'उज्जैन', 'Madhya Pradesh', 23.1765, 75.7885, false],

    // Maharashtra
    ['Mumbai', 'मुंबई', 'Maharashtra', 19.0760, 72.8777, true],
    ['Pune', 'पुणे', 'Maharashtra', 18.5204, 73.8567, true],
    ['Nagpur', 'नागपुर', 'Maharashtra', 21.1458, 79.0882, true],
    ['Nashik', 'नासिक', 'Maharashtra', 19.9975, 73.7898, false],
    ['Chhatrapati Sambhajinagar', 'छत्रपति संभाजीनगर', 'Maharashtra', 19.8762, 75.3433, false],
    ['Thane', 'ठाणे', 'Maharashtra', 19.2183, 72.9781, false],
    ['Navi Mumbai', 'नवी मुंबई', 'Maharashtra', 19.0330, 73.0297, false],
    ['Solapur', 'सोलापुर', 'Maharashtra', 17.6599, 75.9064, false],
    ['Kolhapur', 'कोल्हापुर', 'Maharashtra', 16.7050, 74.2433, false],
    ['Amravati', 'अमरावती', 'Maharashtra', 20.9374, 77.7796, false],

    // Manipur
    ['Imphal', 'इंफाल', 'Manipur', 24.8170, 93.9368, false],

    // Meghalaya
    ['Shillong', 'शिलांग', 'Meghalaya', 25.5788, 91.8933, false],

    // Mizoram
    ['Aizawl', 'आइज़ोल', 'Mizoram', 23.7271, 92.7176, false],

    // Nagaland
    ['Kohima', 'कोहिमा', 'Nagaland', 25.6751, 94.1086, false],
    ['Dimapur', 'दीमापुर', 'Nagaland', 25.9091, 93.7266, false],

    // Odisha
    ['Bhubaneswar', 'भुवनेश्वर', 'Odisha', 20.2961, 85.8245, true],
    ['Cuttack', 'कटक', 'Odisha', 20.4625, 85.8828, false],
    ['Rourkela', 'राउरकेला', 'Odisha', 22.2604, 84.8536, false],

    // Punjab
    ['Amritsar', 'अमृतसर', 'Punjab', 31.6340, 74.8723, false],
    ['Ludhiana', 'लुधियाना', 'Punjab', 30.9010, 75.8573, false],
    ['Jalandhar', 'जालंधर', 'Punjab', 31.3260, 75.5762, false],
    ['Patiala', 'पटियाला', 'Punjab', 30.3398, 76.3869, false],

    // Rajasthan
    ['Jaipur', 'जयपुर', 'Rajasthan', 26.9124, 75.7873, true],
    ['Jodhpur', 'जोधपुर', 'Rajasthan', 26.2389, 73.0243, false],
    ['Udaipur', 'उदयपुर', 'Rajasthan', 24.5854, 73.7125, false],
    ['Kota', 'कोटा', 'Rajasthan', 25.2138, 75.8648, false],
    ['Ajmer', 'अजमेर', 'Rajasthan', 26.4499, 74.6399, false],
    ['Bikaner', 'बीकानेर', 'Rajasthan', 28.0229, 73.3119, false],

    // Sikkim
    ['Gangtok', 'गंगटोक', 'Sikkim', 27.3389, 88.6065, false],

    // Tamil Nadu
    ['Chennai', 'चेन्नई', 'Tamil Nadu', 13.0827, 80.2707, true],
    ['Coimbatore', 'कोयंबटूर', 'Tamil Nadu', 11.0168, 76.9558, true],
    ['Madurai', 'मदुरै', 'Tamil Nadu', 9.9252, 78.1198, false],
    ['Tiruchirappalli', 'तिरुचिरापल्ली', 'Tamil Nadu', 10.7905, 78.7047, false],
    ['Salem', 'सेलम', 'Tamil Nadu', 11.6643, 78.1460, false],
    ['Tirunelveli', 'तिरुनेलवेली', 'Tamil Nadu', 8.7139, 77.7567, false],

    // Telangana
    ['Hyderabad', 'हैदराबाद', 'Telangana', 17.3850, 78.4867, true],
    ['Warangal', 'वारंगल', 'Telangana', 17.9689, 79.5941, false],

    // Tripura
    ['Agartala', 'अगरतला', 'Tripura', 23.8315, 91.2868, false],

    // Uttar Pradesh
    ['Lucknow', 'लखनऊ', 'Uttar Pradesh', 26.8467, 80.9462, true],
    ['Kanpur', 'कानपुर', 'Uttar Pradesh', 26.4499, 80.3319, true],
    ['Agra', 'आगरा', 'Uttar Pradesh', 27.1767, 78.0081, false],
    ['Varanasi', 'वाराणसी', 'Uttar Pradesh', 25.3176, 82.9739, false],
    ['Prayagraj', 'प्रयागराज', 'Uttar Pradesh', 25.4358, 81.8463, false],
    ['Ghaziabad', 'ग़ाज़ियाबाद', 'Uttar Pradesh', 28.6692, 77.4538, false],
    ['Noida', 'नोएडा', 'Uttar Pradesh', 28.5355, 77.3910, false],
    ['Meerut', 'मेरठ', 'Uttar Pradesh', 28.9845, 77.7064, false],
    ['Bareilly', 'बरेली', 'Uttar Pradesh', 28.3670, 79.4304, false],
    ['Aligarh', 'अलीगढ़', 'Uttar Pradesh', 27.8974, 78.0880, false],
    ['Moradabad', 'मुरादाबाद', 'Uttar Pradesh', 28.8386, 78.7733, false],
    ['Gorakhpur', 'गोरखपुर', 'Uttar Pradesh', 26.7606, 83.3732, false],
    ['Jhansi', 'झांसी', 'Uttar Pradesh', 25.4484, 78.5685, false],
    ['Saharanpur', 'सहारनपुर', 'Uttar Pradesh', 29.9680, 77.5450, false],

    // Uttarakhand
    ['Dehradun', 'देहरादून', 'Uttarakhand', 30.3165, 78.0322, false],
    ['Haridwar', 'हरिद्वार', 'Uttarakhand', 29.9457, 78.1642, false],

    // West Bengal
    ['Kolkata', 'कोलकाता', 'West Bengal', 22.5726, 88.3639, true],
    ['Howrah', 'हावड़ा', 'West Bengal', 22.5958, 88.2636, false],
    ['Durgapur', 'दुर्गापुर', 'West Bengal', 23.5204, 87.3119, false],
    ['Asansol', 'आसनसोल', 'West Bengal', 23.6739, 86.9524, false],
    ['Siliguri', 'सिलीगुड़ी', 'West Bengal', 26.7271, 88.3953, false],

    // Union territories
    ['Delhi', 'दिल्ली', 'Delhi', 28.6139, 77.2090, true],
    ['Chandigarh', 'चंडीगढ़', 'Chandigarh', 30.7333, 76.7794, true],
    ['Srinagar', 'श्रीनगर', 'Jammu and Kashmir', 34.0837, 74.7973, false],
    ['Jammu', 'जम्मू', 'Jammu and Kashmir', 32.7266, 74.8570, false],
    ['Leh', 'लेह', 'Ladakh', 34.1526, 77.5771, false],
    ['Puducherry', 'पुडुचेरी', 'Puducherry', 11.9416, 79.8083, false],
    ['Port Blair', 'पोर्ट ब्लेयर', 'Andaman and Nicobar Islands', 11.6234, 92.7265, false],
    ['Silvassa', 'सिलवासा', 'Dadra and Nagar Haveli and Daman and Diu', 20.2766, 73.0169, false],
    ['Kavaratti', 'कवरत्ती', 'Lakshadweep', 10.5593, 72.6358, false],
];
