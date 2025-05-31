import { createApp, ref, reactive, watch } from 'vue';

createApp({
    setup() {
        // --- Authentication State ---
        const user = reactive({ // Use reactive for the user object
            loggedIn: false,
            name: 'Guest',
            role: 'guest' // Possible roles: 'guest', 'shimei', 'admin'
        });

        const loginForm = reactive({ // State for the login form
            username: '',
            password: ''
        });

        const loginError = ref(''); // State for displaying login errors

        // Simulate user data (replace with real authentication later)
        const users = {
            'shimei_member': { password: 'password123', name: 'Membre Shimei', role: 'shimei' },
            'admin_user': { password: 'adminpassword', name: 'Administrateur', role: 'admin' }
        };

        // Login Method
        const login = () => {
            const enteredUsername = loginForm.username.toLowerCase(); // Case-insensitive check
            const userData = users[enteredUsername];

            if (userData && userData.password === loginForm.password) {
                user.loggedIn = true;
                user.name = userData.name;
                user.role = userData.role;
                loginError.value = ''; // Clear any previous error
                // Redirect to a default section after login, e.g., received-missions for non-guests
                changeSection(user.role === 'admin' ? 'shimei-members' : 'received-missions'); // Go to admin page or shimei missions
            } else {
                loginError.value = 'Nom d\'utilisateur ou mot de passe incorrect.';
                // Keep user as guest and current section as login
                user.loggedIn = false;
                user.name = 'Guest';
                user.role = 'guest';
                 currentSection.value = 'login'; // Stay on login screen
            }
        };

        // Logout Method
        const logout = () => {
            user.loggedIn = false;
            user.name = 'Guest';
            user.role = 'guest';
            loginForm.username = ''; // Clear form fields
            loginForm.password = '';
            loginError.value = ''; // Clear error
            changeSection('login'); // Go back to the login section
        };
        // --- End Authentication State & Methods ---


        const currentSection = ref('login'); // Default section should be login
        const missionRequest = reactive({
            requesterName: '', // Added requester name
            rank: '',
            numParticipants: 1,
            participants: [{ name: '', rank: '', details: '' }], // Default participant
            details: ''
        });
        const submissionMessage = ref('');
        const currentYear = ref(new Date().getFullYear());

        // New state for Shimei view (missions submitted via the form)
        const receivedMissions = reactive([]); // Array to store submitted missions

        // List of possible Shimei ranks (Roles within the Shimei organization)
        const shimeiRanks = [ // Renamed from ninjaRanks for clarity
            'Gérant',
            'Co-Gérant',
            'Chef des Missions',
            'Tresorier',
            'Membre',
            'Apprenti'
        ];

        // New list of Ninja ranks (Combat/Skill ranks)
        const ninjaRanksList = [ // Added new list for Ninja ranks dropdown
             'Genin',
             'Genin Confirmé',
             'Chûnin',
             'Kakunin',
             'Tokubetsu-Jonin',
             'Jonin',
             'Commandant-Jonin',
             'Kazekage'
        ];


        // New state for Shimei Members view (Admin)
        const shimeiMembers = reactive([ // Initial list of members
            { name: 'Gaara', rank: 'Gérant', ninjaRank: 'Kazekage', isAnimateur: true },
            { name: 'Temari', rank: 'Co-Gérant', ninjaRank: 'Jonin', isAnimateur: true },
            { name: 'Kankuro', rank: 'Chef des Missions', ninjaRank: 'Jonin', isAnimateur: true },
            { name: 'Baki', rank: 'Membre', ninjaRank: 'Jonin', isAnimateur: false }
        ]);
        const currentMember = reactive({ // State for the add/edit form
            name: '',
            rank: '', // This is for Shimei role (Gérant, Membre, etc.)
            ninjaRank: '' // This is for Ninja rank (Genin, Jonin, etc.)
        });
        // New state for the Animateur select dropdown (string 'Oui'/'Non')
        const currentMemberAnimateurStatus = ref('');

        const editingIndex = ref(null); // Index of the member being edited, null for new member

        // --- State for Standalone Reports ---
        const shimeiReports = reactive([]); // Array to store reports written by Shimei members
        // State for the report add/edit form
        const reportFormTitle = ref('');
        const reportFormRank = ref('');
        const reportFormWriter = ref('');
        const reportFormContent = ref('');
        const editingReportIndex = ref(null); // Index of the report being edited
        // --- End Standalone Reports State ---


        // Watch numParticipants to update the participants array
        watch(() => missionRequest.numParticipants, (newVal) => {
            const currentLength = missionRequest.participants.length;
            // Ensure newVal is a number and at least 1
            newVal = parseInt(newVal, 10);
            if (isNaN(newVal) || newVal < 1) {
                newVal = 1;
                // It's better to also update the model value if it's invalid
                 missionRequest.numParticipants = 1;
            }

            if (newVal > currentLength) {
                // Add new participants
                for (let i = currentLength; i < newVal; i++) {
                    missionRequest.participants.push({ name: '', rank: '', details: '' });
                }
            } else if (newVal < currentLength) {
                // Remove participants
                missionRequest.participants.splice(newVal);
            }
        });

        const changeSection = (sectionId) => {
             // Check if the section requires authentication and the user is logged in
            if (['received-missions', 'shimei-members', 'reports'].includes(sectionId) && !user.loggedIn) {
                // If trying to access a protected section without being logged in, redirect to login
                currentSection.value = 'login';
                loginError.value = 'Veuillez vous connecter pour accéder à cette section.'; // Optional: show a message
            } else if (sectionId === 'shimei-members' && user.role !== 'admin') {
                 // If trying to access the admin section without being an admin, redirect elsewhere
                currentSection.value = 'received-missions'; // Redirect to Shimei missions instead
                loginError.value = 'Accès refusé : vous devez être administrateur pour accéder à cette section.'; // Optional: show a message
            }
            else {
                currentSection.value = sectionId;
                 // Optional: Reset the member form when switching sections
                 if (sectionId !== 'shimei-members') {
                     cancelEdit(); // This is for members
                 }
                 // Optional: Reset the report form when switching sections
                 if (sectionId !== 'reports') {
                    resetReportForm(); // Use the new generic reset
                 }
                 // Clear login error when navigating away from login
                 if (sectionId !== 'login') {
                     loginError.value = '';
                 }
            }
        };

        const submitMissionRequest = () => {
            // Simulate submission: Store the mission data
            const newMission = {
                requesterName: missionRequest.requesterName,
                rank: missionRequest.rank,
                numParticipants: missionRequest.numParticipants,
                // Deep copy participants to avoid reactivity issues if the form is reused
                participants: JSON.parse(JSON.stringify(missionRequest.participants)),
                details: missionRequest.details,
                shimeiNotes: '', // Field for Shimei to add notes in the Received Missions view
                status: 'En attente', // Add status field, default to 'En attente'
                // Removed 'report' field as reports are now standalone or added via the report form
            };
            receivedMissions.push(newMission); // Add to received missions list

            console.log("Mission Request Submitted:", JSON.parse(JSON.stringify(newMission))); // Log the data

            submissionMessage.value = `Demande de mission soumise avec succès par ${missionRequest.requesterName}. En attente de validation et d'assignation par la Shimei. Vous pourrez voir les détails dans la section "Missions Reçues (Shimei)". Note: Ceci est oooooooo, les données sont stockées localement pour démonstration.`;

            // Optional: Clear the form after submission for a new request
            // missionRequest.requesterName = '';
            // missionRequest.rank = '';
            // missionRequest.numParticipants = 1;
            // missionRequest.participants = [{ name: '', rank: '', details: '' }];
            // missionRequest.details = '';
        };

        // Function called by @input on num-participants to trigger watch effect immediately
         const updateParticipants = () => {
            // The watch effect handles the actual array update
            // This function might be redundant now that watch is used, but keeping it doesn't hurt
         };

        // Methods for Shimei actions in 'Received Missions' view
        const validateMission = (index) => {
            if (receivedMissions[index]) {
                receivedMissions[index].status = 'Validée';
                // Optional: Add default note
                if (!receivedMissions[index].shimeiNotes) {
                    receivedMissions[index].shimeiNotes = 'Mission validée par la Shimei.';
                }
                console.log(`Mission ${index + 1} Validated.`);
            }
        };

        const rejectMission = (index) => {
             if (receivedMissions[index]) {
                receivedMissions[index].status = 'Refusée';
                 // Optional: Add default note
                if (!receivedMissions[index].shimeiNotes) {
                    receivedMissions[index].shimeiNotes = 'Mission refusée par la Shimei.';
                }
                 console.log(`Mission ${index + 1} Rejected.`);
            }
        };


        // Methods for Shimei Members (Admin)
        const addMember = () => {
             // Check for required fields
             if (currentMember.name && currentMember.rank && currentMember.ninjaRank && currentMemberAnimateurStatus.value) {
                 shimeiMembers.push({
                     name: currentMember.name,
                     rank: currentMember.rank, // Shimei Role Rank
                     ninjaRank: currentMember.ninjaRank, // Ninja Combat Rank
                     // Convert 'Oui'/'Non' string from select to boolean
                     isAnimateur: currentMemberAnimateurStatus.value === 'Oui'
                 });
                 // Clear the form
                 currentMember.name = '';
                 currentMember.rank = '';
                 currentMember.ninjaRank = '';
                 currentMemberAnimateurStatus.value = ''; // Clear the select value
             } else {
                 alert("Veuillez remplir tous les champs pour le membre.");
             }
        };

        const editMember = (index) => {
             editingIndex.value = index;
             // Populate the form with the selected member's data
             currentMember.name = shimeiMembers[index].name;
             currentMember.rank = shimeiMembers[index].rank; // Shimei Role Rank
             currentMember.ninjaRank = shimeiMembers[index].ninjaRank; // Ninja Combat Rank
             // Set the select value based on the boolean
             currentMemberAnimateurStatus.value = shimeiMembers[index].isAnimateur ? 'Oui' : 'Non';
        };

        const updateMember = () => {
             if (editingIndex.value !== null && currentMember.name && currentMember.rank && currentMember.ninjaRank && currentMemberAnimateurStatus.value) {
                 // Update the member at the editing index
                 shimeiMembers[editingIndex.value].name = currentMember.name;
                 shimeiMembers[editingIndex.value].rank = currentMember.rank; // Shimei Role Rank
                 shimeiMembers[editingIndex.value].ninjaRank = currentMember.ninjaRank; // Ninja Combat Rank
                 // Convert 'Oui'/'Non' string from select to boolean
                 shimeiMembers[editingIndex.value].isAnimateur = currentMemberAnimateurStatus.value === 'Oui';
                 // Reset the form and editing state
                 cancelEdit();
             } else if (editingIndex.value !== null) { // Check if we are in edit mode before showing alert
                 alert("Veuillez remplir tous les champs pour le membre.");
             }
        };

        const deleteMember = (index) => {
             if (confirm(`Êtes-vous sûr de vouloir supprimer ${shimeiMembers[index].name} ?`)) {
                 shimeiMembers.splice(index, 1);
                 // If the deleted member was being edited, cancel the edit
                 if (editingIndex.value === index) {
                     cancelEdit();
                 } else if (editingIndex.value !== null && editingIndex.value > index) {
                     // If a member *after* the deleted one was being edited, adjust the index
                     editingIndex.value--;
                 }
             }
        };

        const cancelEdit = () => {
            editingIndex.value = null;
            currentMember.name = '';
            currentMember.rank = ''; // Clear Shimei Role Rank
            currentMember.ninjaRank = ''; // Clear Ninja Combat Rank
            currentMemberAnimateurStatus.value = ''; // Clear the select value
        };

        // --- Methods for Standalone Reports ---
        const addReport = () => {
            // Check if all required fields in the form are filled
            if (reportFormTitle.value && reportFormRank.value && reportFormWriter.value && reportFormContent.value) {
                shimeiReports.push({
                    title: reportFormTitle.value,
                    rank: reportFormRank.value,
                    writer: reportFormWriter.value,
                    content: reportFormContent.value,
                    date: new Date().toLocaleDateString() // Add current date
                });
                console.log("Report Added:", JSON.parse(JSON.stringify(shimeiReports[shimeiReports.length - 1])));
                resetReportForm(); // Clear the form after adding
            } else {
                alert("Veuillez remplir tous les champs du rapport.");
            }
        };

        const editReport = (index) => {
             if (shimeiReports[index]) {
                editingReportIndex.value = index;
                // Populate the form with the report data for editing
                reportFormTitle.value = shimeiReports[index].title;
                reportFormRank.value = shimeiReports[index].rank;
                reportFormWriter.value = shimeiReports[index].writer;
                reportFormContent.value = shimeiReports[index].content;
             }
        };

        const updateReport = () => {
            // Check if we are in edit mode and all required fields are filled
            if (editingReportIndex.value !== null && reportFormTitle.value && reportFormRank.value && reportFormWriter.value && reportFormContent.value) {
                // Update the report at the editing index
                shimeiReports[editingReportIndex.value].title = reportFormTitle.value;
                shimeiReports[editingReportIndex.value].rank = reportFormRank.value;
                shimeiReports[editingReportIndex.value].writer = reportFormWriter.value;
                shimeiReports[editingReportIndex.value].content = reportFormContent.value;
                // Date is not updated on edit, it remains the creation date
                console.log(`Report ${editingReportIndex.value + 1} Updated.`);
                resetReportForm(); // Clear the form and exit edit mode
            } else if (editingReportIndex.value !== null) {
                 alert("Veuillez remplir tous les champs pour le rapport.");
            }
        };

        const deleteReport = (index) => {
             if (confirm(`Êtes-vous sûr de vouloir supprimer le rapport "${shimeiReports[index].title}" ?`)) {
                 shimeiReports.splice(index, 1);
                 // If the deleted report was being edited, cancel the edit
                 if (editingReportIndex.value === index) {
                     resetReportForm();
                 } else if (editingReportIndex.value !== null && editingReportIndex.value > index) {
                     // If a report *after* the deleted one was being edited, adjust the index
                     editingReportIndex.value--;
                 }
                 console.log(`Report ${index + 1} Deleted.`);
             }
        };


        const resetReportForm = () => {
            reportFormTitle.value = '';
            reportFormRank.value = '';
            reportFormWriter.value = '';
            reportFormContent.value = '';
            editingReportIndex.value = null; // Exit edit mode
        };

        const cancelReportEdit = () => {
            resetReportForm(); // Same as resetting the form
        }
        // --- End Standalone Reports Methods ---


        return {
            currentSection,
            missionRequest,
            submissionMessage,
            currentYear,
            receivedMissions, // Missions submitted via form
            shimeiMembers, // Shimei members list
            currentMember, // State for member add/edit form
            currentMemberAnimateurStatus, // State for animateur select
            editingIndex, // State for member editing index
            shimeiRanks, // List of Shimei ranks
            ninjaRanksList, // List of Ninja ranks

            // Standalone Reports state and methods
            shimeiReports,
            reportFormTitle,
            reportFormRank,
            reportFormWriter,
            reportFormContent,
            editingReportIndex,
            addReport,
            editReport,
            updateReport,
            deleteReport,
            resetReportForm,
            cancelReportEdit,

            changeSection,
            submitMissionRequest,
            updateParticipants,
            validateMission, // Keep these for the received missions section
            rejectMission, // Keep these for the received missions section

            addMember, // Admin methods
            editMember,
            updateMember,
            deleteMember,
            cancelEdit, // Admin method

            // Authentication state and methods
            user,
            loginForm,
            loginError,
            login,
            logout
        };
    }
}).mount('#app');