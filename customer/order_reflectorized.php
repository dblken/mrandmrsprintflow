<?php
/**
 * Reflectorized (Subdivision Stickers / Signages) - Service Order Form
 * PrintFlow - High-End Multi-Step ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$page_title = 'Order Reflectorized Signage - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8 bg-gray-50">
    <div class="container mx-auto px-4" style="max-width: 700px;">
        <!-- Header & Progress -->
        <div class="mb-8" id="pageHeaderSection">
            <div class="flex items-center justify-between mb-6">
                <h1 id="pageTitle" class="text-3xl font-black text-gray-900 uppercase tracking-tighter italic">🚦 Reflectorized Signage</h1>
                <a href="services.php" class="text-sm font-bold text-black border-b-2 border-black">← Back to Services</a>
            </div>
            
            <!-- Progress Indicator -->
            <div class="relative pt-1">
                <div class="flex mb-2 items-center justify-between">
                    <div>
                        <span id="stepBadge" class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-white bg-black">
                            Step 1
                        </span>
                    </div>
                    <div class="text-right">
                        <span id="progressPercent" class="text-xs font-semibold inline-block text-black">
                            Step 1 to Next
                        </span>
                    </div>
                </div>
                <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200">
                    <div id="progressBar" style="width:16%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-black transition-all duration-500"></div>
                </div>
            </div>
        </div>

        <!-- Order Form -->
        <form id="reflectorizedForm" class="relative" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="service_type" value="Reflectorized Signage">

            <!-- SECTION 1: Product Type -->
            <div class="step-section" id="step1">
                <div class="card p-8 shadow-xl border-none">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-black text-white rounded-2xl flex items-center justify-center text-2xl font-bold">1</div>
                        <h2 class="text-2xl font-black uppercase italic">Type of Reflectorized Product</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php 
                        $types = [
                            'Subdivision / Gate Pass (Vehicle Sticker)', 
                            'Plate Number / Temporary Plate',
                            'Street Signage', 
                            'Custom Reflectorized Sign'
                        ];
                        foreach($types as $type): 
                            $isTempPlate = ($type === 'Plate Number / Temporary Plate');
                        ?>
                        <label class="option-card flex flex-col p-5 group transition-all">
                            <input type="radio" name="product_type" value="<?php echo $type; ?>" class="native-hidden-radio" required onchange="updateOptionVisuals(this)">
                            
                            <div class="flex items-center w-full">
                                <div class="radio-indicator">
                                    <div class="radio-dot"></div>
                                </div>
                                <span class="ml-4 font-bold tracking-tight"><?php echo $type; ?></span>
                            </div>
                            
                            <?php if($isTempPlate): ?>
                            <div class="tempPlateFields hidden mt-5 p-5 bg-white rounded-xl shadow-inner w-full space-y-5 border border-gray-200" style="color: #000 !important; cursor: default;" onclick="event.stopPropagation()">
                                
                                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                                    <h4 class="font-black text-[11px] uppercase tracking-widest text-black mb-1">Material Options</h4>
                                    <p class="text-[10px] text-gray-500 mb-4 italic leading-relaxed">Please choose the material before proceeding with your order.</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <label class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-black transition-all">
                                            <input type="radio" name="temp_plate_material" value="Acrylic" class="w-4 h-4 text-black focus:ring-black border-gray-300" onclick="event.stopPropagation()">
                                            <span class="text-xs font-bold text-black flex-1">Acrylic</span>
                                        </label>
                                        <label class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-black transition-all">
                                            <input type="radio" name="temp_plate_material" value="Aluminum Sheet" class="w-4 h-4 text-black focus:ring-black border-gray-300" onclick="event.stopPropagation()">
                                            <span class="text-xs font-bold text-black flex-1">Aluminum Sheet</span>
                                        </label>
                                        <label class="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-black transition-all">
                                            <input type="radio" name="temp_plate_material" value="Aluminum Coated (Steel Plate)" class="w-4 h-4 text-black focus:ring-black border-gray-300" onclick="event.stopPropagation()">
                                            <span class="text-xs font-bold text-black leading-tight flex-1">Aluminum Coated<br><span class="text-[9px] text-gray-500 font-normal">(Steel Plate)</span></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Plate Number *</label>
                                        <input type="text" name="temp_plate_number" id="temp_plate_number"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:border-black focus:ring-1 focus:ring-black focus:outline-none transition-all font-bold text-black text-sm" 
                                            placeholder="Must match OR/CR"
                                            onclick="event.stopPropagation()">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">TEMPORARY PLATE text</label>
                                        <input type="text" name="temp_plate_text"
                                            class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-lg focus:outline-none transition-all font-bold text-gray-600 text-sm" 
                                            value="TEMPORARY PLATE" readonly
                                            onclick="event.stopPropagation()">
                                        <p class="text-[9px] text-gray-400 mt-1 italic">Appears on design.</p>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">MV File Number (Optional)</label>
                                        <input type="text" name="mv_file_number"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:border-black focus:ring-1 focus:ring-black focus:outline-none transition-all font-bold text-black text-sm" 
                                            placeholder="Enter MV File Number"
                                            onclick="event.stopPropagation()">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Dealer Name (Optional)</label>
                                        <input type="text" name="dealer_name"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:border-black focus:ring-1 focus:ring-black focus:outline-none transition-all font-bold text-black text-sm" 
                                            placeholder="Enter Dealer or Shop Name"
                                            onclick="event.stopPropagation()">
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($type === 'Subdivision / Gate Pass (Vehicle Sticker)'): ?>
                            <div class="gatePassFields hidden mt-5 p-5 bg-white rounded-xl shadow-inner w-full border border-gray-200" style="color: #000 !important; cursor: default;" onclick="event.stopPropagation()">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Subdivision / Company Name *</label>
                                        <input type="text" name="gate_pass_subdivision" id="gate_pass_subdivision"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:border-black focus:ring-1 focus:ring-black focus:outline-none transition-all font-bold text-black text-sm" 
                                            placeholder="e.g. GREEN VALLEY SUBDIVISION"
                                            onclick="event.stopPropagation()">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Gate Pass Number / Sticker *</label>
                                        <input type="text" name="gate_pass_number" id="gate_pass_number"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:border-black focus:ring-1 focus:ring-black focus:outline-none transition-all font-bold text-black text-sm" 
                                            placeholder="e.g. GP-0215"
                                            onclick="event.stopPropagation()">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Plate Number of Vehicle *</label>
                                        <input type="text" name="gate_pass_plate" id="gate_pass_plate"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:border-black focus:ring-1 focus:ring-black focus:outline-none transition-all font-bold text-black text-sm" 
                                            placeholder="e.g. ABC 1234"
                                            onclick="event.stopPropagation()">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Year / Validity *</label>
                                        <input type="text" name="gate_pass_year" id="gate_pass_year"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:border-black focus:ring-1 focus:ring-black focus:outline-none transition-all font-bold text-black text-sm" 
                                            placeholder="e.g. VALID UNTIL: 2026"
                                            onclick="event.stopPropagation()">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Vehicle Type (Optional)</label>
                                        <select name="gate_pass_vehicle_type" 
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:border-black focus:ring-1 focus:ring-black focus:outline-none transition-all font-bold text-black text-sm"
                                            onclick="event.stopPropagation()">
                                            <option value="">Select Type</option>
                                            <option value="Car">Car</option>
                                            <option value="Motorcycle">Motorcycle</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-8 flex justify-end">
                        <button type="button" onclick="nextStep(2)" class="btn-black px-8 py-3 uppercase tracking-widest rounded-xl shadow-2xl hover:bg-gray-800 transition-all flex items-center gap-2 text-sm">Continue <span class="text-xl">→</span></button>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Size & Shape -->
            <div class="step-section hidden" id="step2">
                <div class="card p-8 shadow-xl border-none">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-black text-white rounded-2xl flex items-center justify-center text-2xl font-bold">2</div>
                        <h2 class="text-2xl font-black uppercase italic">Size & Shape</h2>
                    </div>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-2">Exact Size (Width × Height) *</label>
                                <input type="text" name="dimensions" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black focus:outline-none transition-all font-bold" placeholder="e.g. 12 x 18" required>
                            </div>
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-2">Unit *</label>
                                <select name="unit" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black focus:outline-none transition-all font-bold">
                                    <option value="inches">Inches</option>
                                    <option value="cm">Centimeters</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="logoSection" class="hidden border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center bg-gray-50 hover:bg-white transition-all cursor-pointer relative group">
                            <input type="file" name="gate_pass_logo" id="gate_pass_logo" class="absolute inset-0 opacity-0 cursor-pointer" onchange="updateLogoPreview(this)">
                            <div class="space-y-4">
                                <div class="text-4xl group-hover:scale-110 transition-transform duration-300">🖼️</div>
                                <div class="logo-text-area">
                                    <p class="font-black uppercase tracking-widest text-sm text-black logo-label">Reference Design / Layout Concept</p>
                                    <p class="text-xs text-gray-500 mt-1">Drag & drop or click to browse</p>
                                </div>
                                <div class="logo-preview-area hidden flex items-center justify-center gap-3 bg-white p-3 rounded-xl border border-gray-100">
                                    <span class="logo-file-name text-sm font-bold text-black italic"></span>
                                    <button type="button" onclick="removeLogo(this)" class="text-red-500 font-bold hover:scale-110 transition-all">✕</button>
                                </div>
                            </div>
                        </div>

                        <!-- Gate Pass Specific Quantity (Moved to Step 2) -->
                        <div id="gatePassQuantity" class="hidden animate-fade-in pt-4 border-t border-gray-100">
                            <label class="block text-xs font-black uppercase text-gray-500 mb-4 tracking-widest text-center">Quantity Required *</label>
                            <input type="number" name="quantity_gatepass" id="quantity_gatepass" min="1" value="1" 
                                class="w-48 mx-auto px-8 py-6 bg-gray-50 border-2 border-gray-100 rounded-3xl focus:border-black font-black text-4xl text-center block">
                        </div>

                        <!-- Signage Only Fields -->
                        <div id="signageOptions" class="hidden space-y-4 pt-4 border-t border-gray-100">
                            <div class="flex gap-8">
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <span class="text-sm font-black uppercase text-gray-500 group-hover:text-black">With Border?</span>
                                    <div class="relative inline-block w-12 h-6 transition duration-200 ease-in-out bg-gray-200 rounded-full">
                                        <input type="checkbox" name="with_border" class="absolute w-6 h-6 transition duration-200 ease-in-out transform bg-white border-2 border-gray-200 rounded-full appearance-none cursor-pointer checked:translate-x-6 checked:bg-black checked:border-black">
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <span class="text-sm font-black uppercase text-gray-500 group-hover:text-black">Rounded Corners?</span>
                                    <div class="relative inline-block w-12 h-6 transition duration-200 ease-in-out bg-gray-200 rounded-full">
                                        <input type="checkbox" name="rounded_corners" class="absolute w-6 h-6 transition duration-200 ease-in-out transform bg-white border-2 border-gray-200 rounded-full appearance-none cursor-pointer checked:translate-x-6 checked:bg-black checked:border-black">
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8 flex justify-between">
                        <button type="button" onclick="prevStep(1)" class="px-6 py-3 bg-white text-black font-black uppercase tracking-widest rounded-xl border-2 border-gray-200 hover:bg-gray-50 transition-all text-sm">Back</button>
                        <button type="button" onclick="nextStep(3)" class="btn-black px-8 py-3 uppercase tracking-widest rounded-xl shadow-2xl hover:bg-gray-800 transition-all flex items-center gap-2 text-sm">Continue <span class="text-xl">→</span></button>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Reflectorized Material Type -->
            <div class="step-section hidden" id="step3">
                <div class="card p-8 shadow-xl border-none">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-black text-white rounded-2xl flex items-center justify-center text-2xl font-bold">3</div>
                        <h2 class="text-2xl font-black uppercase italic">Reflectorized Material Type</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <?php 
                        $materials = [
                            '3M Brand' => 'Premium brand for Printed Reflectorized signage.',
                            'Kiwalite' => 'Ideal for Cut-Out Sticker applications.'
                        ];
                        foreach($materials as $name => $desc): ?>
                        <label class="option-card flex flex-col p-6 group">
                            <input type="radio" name="material_type" value="<?php echo $name; ?>" class="native-hidden-radio" required onchange="updateOptionVisuals(this)">
                            <div class="flex items-center">
                                <div class="radio-indicator">
                                    <div class="radio-dot"></div>
                                </div>
                                <span class="ml-4 font-black tracking-tight"><?php echo $name; ?></span>
                            </div>
                            <p class="ml-10 mt-1 text-[11px] text-gray-500 italic group-[.active]:text-gray-300"><?php echo $desc; ?></p>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-8 flex justify-between">
                        <button type="button" onclick="prevStep(2)" class="px-6 py-3 bg-white text-black font-black uppercase tracking-widest rounded-xl border-2 border-gray-200 hover:bg-gray-50 transition-all text-sm">Back</button>
                        <button type="button" onclick="nextStep(4)" class="btn-black px-8 py-3 uppercase tracking-widest rounded-xl shadow-2xl hover:bg-gray-800 transition-all flex items-center gap-2 text-sm">Continue <span class="text-xl">→</span></button>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Design / Personalization Details -->
            <div class="step-section hidden" id="step4">
                <div class="card p-8 shadow-xl border-none">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-black text-white rounded-2xl flex items-center justify-center text-2xl font-bold">4</div>
                        <h2 class="text-2xl font-black uppercase italic">Design / Personalization</h2>
                    </div>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="subdivisionFields">
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-2">Subdivision Name *</label>
                                <input type="text" name="subdivision_name" id="subdivision_name_input" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black focus:outline-none transition-all font-bold" required>
                            </div>
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-2">Year Valid</label>
                                <input type="text" name="year_valid" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black focus:outline-none transition-all font-bold" placeholder="e.g. 2024">
                            </div>
                        </div>

                        <div id="signageLogoSection" class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center bg-gray-50 hover:bg-white transition-all cursor-pointer relative group">
                            <input type="file" name="signage_logo" id="signage_logo" class="absolute inset-0 opacity-0 cursor-pointer" onchange="updateLogoPreview(this)">
                            <div class="space-y-4">
                                <div class="text-4xl group-hover:scale-110 transition-transform duration-300">🖼️</div>
                                <div class="logo-text-area">
                                    <p class="font-black uppercase tracking-widest text-sm text-black logo-label">Upload Logo (Optional)</p>
                                    <p class="text-xs text-gray-500 mt-1">Drag & drop or click to browse</p>
                                </div>
                                <div class="logo-preview-area hidden flex items-center justify-center gap-3 bg-white p-3 rounded-xl border border-gray-100">
                                    <span class="logo-file-name text-sm font-bold text-black italic"></span>
                                    <button type="button" onclick="removeLogo(this)" class="text-red-500 font-bold hover:scale-110 transition-all">✕</button>
                                </div>
                            </div>
                        </div>

                        <!-- gatePassQuantity moved to step 2 -->

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="stickerFields">
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-2">Sticker / Serial Number</label>
                                <input type="text" name="serial_number" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black font-bold">
                            </div>
                            <div id="plateNumberAlternative" class="hidden">
                                <label class="block text-xs font-black uppercase text-gray-500 mb-2">Plate Number (if Vehicle)</label>
                                <input type="text" name="plate_number_alt" id="plate_number_alt" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black font-bold">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="ownerFields">
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-2">Homeowner Name</label>
                                <input type="text" name="homeowner_name" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black font-bold">
                            </div>
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-2">Block & Lot</label>
                                <input type="text" name="block_lot" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black font-bold">
                            </div>
                        </div>

                        <!-- Signage Text Fields -->
                        <div id="signageTextSection" class="hidden space-y-6 pt-6 border-t border-gray-100">
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-2">Text Content *</label>
                                <textarea name="text_content" rows="3" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black font-bold" placeholder="e.g. NO PARKING, ONE WAY"></textarea>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-black uppercase text-gray-500 mb-2">Arrow Direction</label>
                                    <select name="arrow_direction" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black font-bold">
                                        <option value="None">None</option>
                                        <option value="Left">Left</option>
                                        <option value="Right">Right</option>
                                        <option value="Straight">Straight</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-black uppercase text-gray-500 mb-2">Font Preference</label>
                                    <input type="text" name="font_preference" class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-black font-bold" placeholder="e.g. Arial Black, Bold Sans">
                                </div>
                            </div>
                        </div>

                        <!-- Signage Logistics -->
                        <div id="signageLogisticsSection" class="hidden pt-6 border-t border-gray-100">
                            <label class="block text-xs font-black uppercase text-gray-500 mb-4 tracking-widest text-center">Quantity Required *</label>
                            <input type="number" name="quantity_signage" id="quantity_signage" min="1" value="1" class="w-48 mx-auto px-8 py-6 bg-gray-50 border-2 border-gray-100 rounded-3xl focus:border-black font-black text-4xl text-center block" required>
                        </div>
                    </div>
                    <div class="mt-8 flex justify-between">
                        <button type="button" onclick="prevStep(3)" class="px-8 py-4 bg-white text-black font-black uppercase tracking-widest rounded-xl border-2 border-gray-100 hover:bg-gray-50 transition-all">Back</button>
                        <button type="button" onclick="nextStep(5)" class="btn-black px-8 py-3 uppercase tracking-widest rounded-xl shadow-2xl hover:bg-gray-800 transition-all flex items-center gap-2 text-sm">Continue <span class="text-xl">→</span></button>
                    </div>
                </div>
            </div>

            <!-- SECTION 5: Order Details & Logistics -->
            <div class="step-section hidden" id="step5">
                <div class="card p-8 shadow-xl border-none">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-black text-white rounded-2xl flex items-center justify-center text-2xl font-bold">5</div>
                        <h2 class="text-2xl font-black uppercase italic">Order Details & Logistics</h2>
                    </div>
                    <div class="space-y-10">
                        <!-- Colors -->
                        <div id="colorSection" class="space-y-10">
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-4 tracking-widest">Reflective Material Color (Required)</label>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <?php 
                                $colors = [
                                    'White' => 'bg-white', 
                                    'Yellow' => 'bg-yellow-400', 
                                    'Red' => 'bg-red-500', 
                                    'Blue' => 'bg-blue-600', 
                                    'Green' => 'bg-green-600', 
                                    'Custom' => 'bg-gradient-to-br from-gray-400 to-gray-600'
                                ];
                                foreach($colors as $name => $classes): ?>
                                <label class="option-card flex items-center p-4">
                                    <input type="radio" name="reflective_color" value="<?php echo $name; ?>" class="native-hidden-radio" required onchange="updateOptionVisuals(this)">
                                    <div class="flex items-center w-full">
                                        <div class="w-8 h-8 rounded-lg border-2 border-gray-100 <?php echo $classes; ?> flex-shrink-0 relative overflow-hidden">
                                            <div class="absolute inset-0 bg-black text-white flex items-center justify-center opacity-0 group-[.active]:opacity-100 transition-opacity">
                                                <span class="text-xs font-bold">✓</span>
                                            </div>
                                        </div>
                                        <span class="ml-4 font-black uppercase text-xs tracking-widest"><?php echo $name; ?></span>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                        <hr class="border-gray-100">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start" id="quantityNumberingSection">
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-4 tracking-widest">Quantity Required *</label>
                                <input type="number" name="quantity" id="quantity" min="1" value="1" class="w-full px-8 py-6 bg-gray-50 border-2 border-gray-100 rounded-3xl focus:border-black font-black text-4xl text-center" required>
                            </div>
                            <div class="p-6 bg-gray-50 rounded-3xl space-y-4">
                                <label class="flex items-center justify-between cursor-pointer group">
                                    <span class="font-black uppercase text-xs text-gray-700 group-hover:text-black tracking-widest">Individual Numbering?</span>
                                    <div class="relative inline-block w-14 h-8 transition duration-200 ease-in-out bg-gray-300 rounded-full">
                                        <input type="checkbox" name="with_numbering" class="absolute w-8 h-8 transition duration-200 ease-in-out transform bg-white border-4 border-gray-300 rounded-full appearance-none cursor-pointer checked:translate-x-6 checked:bg-black checked:border-black" onchange="toggleNumberingField()">
                                    </div>
                                </label>
                                <div id="numberingSection" class="hidden pt-4 border-t border-gray-200 animate-fade-in">
                                    <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Starting Number</label>
                                    <input type="text" name="starting_number" class="w-full px-5 py-4 bg-white border-2 border-gray-100 rounded-xl focus:border-black font-bold" placeholder="e.g. 0001">
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        <!-- Mounting Options (Signage Only) -->
                        <div id="signageOnlyMounting" class="space-y-6">
                            <label class="block text-xs font-black uppercase text-gray-500 tracking-widest">Mounting Options (Signage Only)</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php 
                                $mounts = ['Aluminum Plate Backing', 'Acrylic Backing', 'Metal Frame', 'Sticker Only (No Board)'];
                                foreach($mounts as $mount): ?>
                                <label class="option-card flex items-center p-5">
                                    <input type="radio" name="mounting_option" value="<?php echo $mount; ?>" class="native-hidden-radio" onchange="updateOptionVisuals(this)">
                                    <div class="flex items-center w-full">
                                        <div class="radio-indicator">
                                            <div class="radio-dot"></div>
                                        </div>
                                        <span class="ml-4 font-bold tracking-tight"><?php echo $mount; ?></span>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>


                        <!-- Special Instructions -->
                        <div class="grid grid-cols-1 gap-6" id="specialInstructionsSection">
                            <div>
                                <label class="block text-xs font-black uppercase text-gray-500 mb-3 tracking-widest">Special Instructions</label>
                                <textarea name="other_instructions" id="other_instructions" rows="3" class="w-full px-6 py-5 bg-gray-50 border-2 border-gray-100 rounded-3xl focus:border-black font-bold text-lg" placeholder="Anything else we should know (e.g., custom colors, specific font, or layout requests)?"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="mt-12 flex justify-between">
                        <button type="button" onclick="prevStep(4)" class="px-6 py-3 bg-white text-black font-black uppercase tracking-widest rounded-xl border-2 border-gray-200 hover:bg-gray-50 transition-all text-sm">Back</button>
                        <button type="button" onclick="prepareSummary()" class="btn-black px-8 py-3 uppercase tracking-widest rounded-xl shadow-2xl hover:bg-gray-800 transition-all flex items-center gap-2 text-sm">Review Order <span class="text-xl">→</span></button>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
// Update selection visuals
function updateOptionVisuals(input) {
    const container = input.closest('.grid') || input.closest('form');
    const name = input.name;
    const radios = container.querySelectorAll(`input[name="${name}"]`);
    
    radios.forEach(r => {
        const card = r.closest('.option-card');
        if (card) {
            if (r.checked) card.classList.add('active');
            else card.classList.remove('active');
        }
    });

    // Call dynamic toggles if they exist for specific names
    if (name === 'product_type') typeof toggleSignageFields === 'function' && toggleSignageFields();
}

let currentStep = 1;
const totalSteps = 5; // Removed Step 6

function updateProgress() {
    const type = document.querySelector('input[name="product_type"]:checked')?.value || '';
    const isTempPlate = type.includes('Temporary Plate');
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass Sticker');
    
    let displayStep = currentStep;
    let displayTotal = totalSteps;
    
    if(isTempPlate) {
        displayTotal = 2;
        displayStep = currentStep === 1 ? 1 : 2;
    } else if(isGatePass) {
        displayTotal = 2; // Selection and Details
        if(currentStep === 1) displayStep = 1;
        else if(currentStep === 2) displayStep = 2;
    }
    
    const percent = (currentStep / totalSteps) * 100;
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').innerText = 'Step ' + displayStep + ' to Next';
    document.getElementById('stepBadge').innerText = 'Step ' + displayStep;

    // Extreme Clean logic removed as Step 6 is deleted
}

function nextStep(step) {
    if(!validateCurrentStep()) return;
    
    const type = document.querySelector('input[name="product_type"]:checked')?.value || '';
    const isTempPlate = type.includes('Temporary Plate');
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass Sticker');
    const isSignage = type.includes('Sign') || type.includes('Street');
    
    // Adjust target step bounds
    let target = step;
    
    // Direct skips from Step 1 or 2
    if(isTempPlate && currentStep === 1) {
        submitReflectorizedOrder();
        return;
    } else if(isGatePass && currentStep === 2) {
        submitReflectorizedOrder();
        return;
    } else if(isSignage && currentStep === 4) {
        submitReflectorizedOrder();
        return;
    }
    
    // For other products, if they hit the end of their logic
    if(target > 5) {
        submitReflectorizedOrder();
        return;
    }
    
    if(target >= 7) target = 6;

    document.getElementById('step' + currentStep).classList.add('hidden');
    document.getElementById('step' + target).classList.remove('hidden');
    currentStep = target;
    updateProgress();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prevStep(step) {
    const type = document.querySelector('input[name="product_type"]:checked')?.value || '';
    const isTempPlate = type.includes('Temporary Plate');
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass Sticker');
    
    let target = step;
    // Step 6 prev logic removed

    document.getElementById('step' + currentStep).classList.add('hidden');
    document.getElementById('step' + target).classList.remove('hidden');
    currentStep = target;
    updateProgress();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateCurrentStep() {
    const section = document.getElementById('step' + currentStep);
    const required = section.querySelectorAll('[required]');
    let valid = true;
    
    required.forEach(el => {
        // Skip validation if the element or its parent is hidden
        if (el.offsetParent === null) return;

        if(el.type === 'radio') {
            const name = el.name;
            const checked = section.querySelector(`input[name="${name}"]:checked`);
            if(!checked) {
                valid = false;
                el.closest('div').classList.add('ring-2', 'ring-red-500', 'rounded-lg');
            } else {
                el.closest('div').classList.remove('ring-2', 'ring-red-500');
            }
        } else if(!el.value) {
            valid = false;
            el.classList.add('border-red-500');
        } else {
            el.classList.remove('border-red-500');
        }
    });

    if(!valid) {
        alert('Please fill in all required fields marked with *');
    }
    return valid;
}

function toggleSignageFields() {
    const type = document.querySelector('input[name="product_type"]:checked')?.value || '';
    
    // Determine if it's signage based on product_type
    const isSignage = type.includes('Sign') || type.includes('Street');
    const isTempPlate = type.includes('Temporary Plate');
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass Sticker');
    const isVehicle = (type.includes('Sticker') || type.includes('Vehicle')) && !isTempPlate && !isGatePass;

    const signageOptions = document.getElementById('signageOptions');
    const signageText = document.getElementById('signageTextSection');
    const stickerFields = document.getElementById('stickerFields');
    const ownerFields = document.getElementById('ownerFields');
    const signageMountingSection = document.getElementById('signageOnlyMounting');
    const signageLogisticsSection = document.getElementById('signageLogisticsSection');
    
    const subdivisionFields = document.getElementById('subdivisionFields');
    const logoSection = document.getElementById('logoSection');
    const subdivisionNameInput = document.getElementById('subdivision_name_input');
    
    // Update Page Title
    const pageTitle = document.getElementById('pageTitle');
    if(pageTitle) {
        if(type) {
            pageTitle.innerText = '🚦 Reflectorized Signage / ' + type;
        } else {
            pageTitle.innerText = '🚦 Reflectorized Signage';
        }
    }
    
    // Toggle nested fields manually if CSS isn't handling it immediately
    const logoLabel = document.getElementById('logoLabel');
    if(logoLabel) {
        logoLabel.innerText = isGatePass ? 'Reference Design / Layout Concept' : 'Upload Logo (Optional)';
    }

    const gatePassQuantity = document.getElementById('gatePassQuantity');
    if(gatePassQuantity) {
        if(isGatePass) gatePassQuantity.classList.remove('hidden');
        else gatePassQuantity.classList.add('hidden');
    }

    if(signageLogisticsSection) {
        if(isSignage) signageLogisticsSection.classList.remove('hidden');
        else signageLogisticsSection.classList.add('hidden');
    }

    const quantityNumberingSection = document.getElementById('quantityNumberingSection');
    if(quantityNumberingSection) {
        if(isGatePass) quantityNumberingSection.classList.add('hidden');
        else quantityNumberingSection.classList.remove('hidden');
    }

    document.querySelectorAll('.tempPlateFields').forEach(el => {
        if(isTempPlate && el.closest('label').querySelector('input').checked) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    });

    document.querySelectorAll('.gatePassFields').forEach(el => {
        if(isGatePass && el.closest('label').querySelector('input').checked) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    });

    const tempPlateNumberInput = document.getElementById('temp_plate_number');
    if(tempPlateNumberInput) tempPlateNumberInput.required = isTempPlate;
    
    const tempPlateMaterials = document.querySelectorAll('input[name="temp_plate_material"]');
    tempPlateMaterials.forEach(input => input.required = isTempPlate);

    const gatePassReqFields = ['gate_pass_subdivision', 'gate_pass_number', 'gate_pass_plate', 'gate_pass_year'];
    gatePassReqFields.forEach(id => {
        const el = document.getElementById(id);
        if(el) el.required = isGatePass;
    });

    const shapeSection = document.getElementById('shapeSection');
    const colorSection = document.getElementById('colorSection');
    const shapeInputs = document.querySelectorAll('input[name="shape"]');
    const materialInputs = document.querySelectorAll('input[name="material_type"]');
    const reflectiveColorInputs = document.querySelectorAll('input[name="reflective_color"]');
    const dimensionsInput = document.querySelector('input[name="dimensions"]');

    // UI visibility logic
    if(isTempPlate || isGatePass) {
        subdivisionFields?.classList.add('hidden');
        if(isTempPlate) logoSection?.classList.add('hidden');
        else logoSection?.classList.remove('hidden');
        stickerFields?.classList.add('hidden');
        ownerFields?.classList.add('hidden');
        signageOptions?.classList.add('hidden');
        signageText?.classList.add('hidden');
        signageMountingSection?.classList.add('hidden');
        
        if(isTempPlate) {
            shapeSection?.classList.add('hidden');
            colorSection?.classList.add('hidden');
            shapeInputs.forEach(input => input.required = false);
            materialInputs.forEach(input => input.required = false);
            reflectiveColorInputs.forEach(input => input.required = false);
            if(dimensionsInput) dimensionsInput.required = false;
        } else if(isGatePass) {
            shapeSection?.classList.remove('hidden');
            colorSection?.classList.add('hidden'); 
            shapeInputs.forEach(input => input.required = true);
            materialInputs.forEach(input => input.required = false); // Skipped step 3
            reflectiveColorInputs.forEach(input => input.required = false);
            if(dimensionsInput) dimensionsInput.required = true;
            
            // Show logo and quantity in Step 2
            logoSection?.classList.remove('hidden');
            document.getElementById('gatePassQuantity')?.classList.remove('hidden');
            if(logoLabel) logoLabel.innerText = "Reference Design / Layout Concept";
        } else {
            shapeSection?.classList.remove('hidden');
            colorSection?.classList.remove('hidden');
            shapeInputs.forEach(input => input.required = true);
            materialInputs.forEach(input => input.required = true);
            reflectiveColorInputs.forEach(input => input.required = true);
            if(dimensionsInput) dimensionsInput.required = true;
        }
        
        if(subdivisionNameInput) subdivisionNameInput.required = false;
    } else if(isSignage) {
        subdivisionFields?.classList.add('hidden');
        logoSection?.classList.add('hidden');
        signageOptions?.classList.remove('hidden');
        signageText?.classList.remove('hidden');
        signageMountingSection?.classList.remove('hidden');
        stickerFields?.classList.add('hidden');
        ownerFields?.classList.add('hidden');
        
        shapeSection?.classList.remove('hidden');
        colorSection?.classList.remove('hidden');
        shapeInputs.forEach(input => input.required = true);
        materialInputs.forEach(input => input.required = true);
        reflectiveColorInputs.forEach(input => input.required = true);
        if(dimensionsInput) dimensionsInput.required = true;

        if(subdivisionNameInput) subdivisionNameInput.required = false;
    } else if(isVehicle) {
        subdivisionFields?.classList.remove('hidden');
        logoSection?.classList.remove('hidden');
        signageOptions?.classList.add('hidden');
        signageText?.classList.add('hidden');
        signageMountingSection?.classList.add('hidden');
        stickerFields?.classList.remove('hidden');
        ownerFields?.classList.remove('hidden');
        
        shapeSection?.classList.remove('hidden');
        colorSection?.classList.remove('hidden');
        shapeInputs.forEach(input => input.required = true);
        materialInputs.forEach(input => input.required = true);
        reflectiveColorInputs.forEach(input => input.required = true);
        if(dimensionsInput) dimensionsInput.required = true;
        
        if(subdivisionNameInput) subdivisionNameInput.required = true;
    } else {
        // Default / Custom
        subdivisionFields?.classList.remove('hidden');
        logoSection?.classList.remove('hidden');
        signageOptions?.classList.remove('hidden');
        signageText?.classList.remove('hidden');
        signageMountingSection?.classList.remove('hidden');
        stickerFields?.classList.remove('hidden');
        ownerFields?.classList.remove('hidden');
        
        shapeSection?.classList.remove('hidden');
        colorSection?.classList.remove('hidden');
        shapeInputs.forEach(input => input.required = true);
        materialInputs.forEach(input => input.required = true);
        reflectiveColorInputs.forEach(input => input.required = true);
        if(dimensionsInput) dimensionsInput.required = true;
        
        if(subdivisionNameInput) subdivisionNameInput.required = true;
    }
}

function toggleNumberingField() {
    const checked = document.querySelector('input[name="with_numbering"]').checked;
    const section = document.getElementById('numberingSection');
    if(checked) {
        section.classList.remove('hidden');
    } else {
        section.classList.add('hidden');
    }
}


function updateLogoPreview(input) {
    const file = input.files[0];
    const container = input.closest('.group');
    const textArea = container.querySelector('.logo-text-area');
    const previewArea = container.querySelector('.logo-preview-area');
    const fileName = container.querySelector('.logo-file-name');
    
    if(file) {
        textArea?.classList.add('hidden');
        previewArea?.classList.remove('hidden');
        if(fileName) fileName.innerText = file.name;
    }
}

function removeLogo(btn) {
    const container = btn.closest('.group');
    const fileInput = container.querySelector('input[type="file"]');
    const textArea = container.querySelector('.logo-text-area');
    const previewArea = container.querySelector('.logo-preview-area');
    
    if(fileInput) fileInput.value = '';
    textArea?.classList.remove('hidden');
    previewArea?.classList.add('hidden');
}

function updateColorText(id, val) {
    document.getElementById(id).value = val.toUpperCase();
}

function submitReflectorizedOrder() {
    const form = document.getElementById('reflectorizedForm');
    const type = document.querySelector('input[name="product_type"]:checked')?.value || '';
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass Sticker');

    if(isGatePass) {
        const gQty = document.getElementById('quantity_gatepass').value;
        document.getElementById('quantity').value = gQty;
    }

    // Show a global loader or button loader if possible
    // Since we are redirecting, we just trigger the submit logic
    // We'll call the existing listener logic
    const event = new Event('submit', { cancelable: true });
    form.dispatchEvent(event);
}

document.getElementById('reflectorizedForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const loader = document.getElementById('loader');
    
    if (btn) btn.disabled = true;
    if (btnText) btnText.classList.add('hidden');
    if (loader) loader.classList.remove('hidden');

    const formData = new FormData(this);
    
    fetch('api_add_to_cart_reflectorized.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            window.location.href = 'order_review.php?item=' + data.item_key;
        } else {
            alert('Error: ' + data.message);
            if (btn) btn.disabled = false;
            if (btnText) btnText.classList.remove('hidden');
            if (loader) loader.classList.add('hidden');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An unexpected error occurred. Please try again.');
        if (btn) btn.disabled = false;
        if (btnText) btnText.classList.remove('hidden');
        if (loader) loader.classList.add('hidden');
    });
});
</script>

<style>
.step-section { animation: slideIn 0.5s ease-out; }
@keyframes slideIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #888; }
.animate-fade-in { animation: fadeIn 0.3s ease-in; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Robust Option Cards */
.option-card {
    position: relative;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: #ffffff;
    border: 2px solid #f3f4f6;
    border-radius: 1rem;
    overflow: hidden;
}
.option-card:hover { border-color: #000; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
.option-card.active {
    background: #000000 !important;
    color: #ffffff !important;
    border-color: #000000 !important;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
}
.radio-indicator {
    width: 24px;
    height: 24px;
    border: 2px solid #d1d5db;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    transition: all 0.3s;
    flex-shrink: 0;
}
.option-card.active .radio-indicator { border-color: #ffffff; }
.radio-dot {
    width: 10px;
    height: 10px;
    background: #000000;
    border-radius: 50%;
    opacity: 0;
    transform: scale(0);
    transition: all 0.3s;
}
.option-card.active .radio-dot { opacity: 1; transform: scale(1); }
.native-hidden-radio {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}
.btn-black {
    background-color: #000000 !important;
    color: #ffffff !important;
    font-weight: 900 !important;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

