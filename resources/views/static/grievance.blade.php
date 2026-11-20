<x-static-page title="Grievance officer" description="How to contact the {{ site_name() }} Grievance Officer and how we handle complaints under the IT Rules, 2021." updated="5 November 2026">
    @if (setting('legal.grievance_email') === 'grievance@nextgennews.in')
    <p><strong>Note: the officer details below are placeholders. Add the appointed officer's details in Admin → Settings → Legal.</strong></p>
    @endif

    <h2>Who handles complaints</h2>
    <p>Under the Information Technology Act, 2000 and the Information Technology (Intermediary Guidelines and Digital Media Ethics Code) Rules, 2021 – including Rule 3(2) and the grievance redressal mechanism for digital news publishers under Part III (Rule 11) – {{ site_name() }} has a Grievance Officer. You can contact the officer about our news content, comments and polls posted by users, or how we handle your personal data.</p>

    <table>
        <tr>
            <th>Name</th>
            <td>{{ setting('legal.grievance_name') }}</td>
        </tr>
        <tr>
            <th>Designation</th>
            <td>Grievance Officer, {{ site_name() }}</td>
        </tr>
        <tr>
            <th>Email</th>
            <td><a href="mailto:{{ setting('legal.grievance_email') }}">{{ setting('legal.grievance_email') }}</a></td>
        </tr>
        <tr>
            <th>Phone</th>
            <td>{{ setting('legal.grievance_phone') }}</td>
        </tr>
        <tr>
            <th>Address</th>
            <td>{{ site_name() }}, {{ setting('legal.grievance_address') }}</td>
        </tr>
    </table>

    <h2>How quickly we respond</h2>
    <ul>
        <li><strong>Acknowledgement:</strong> within 24 hours of receiving your complaint.</li>
        <li><strong>Resolution:</strong> within 15 days of receiving your complaint. We will tell you what we decided and why.</li>
        <li><strong>Urgent complaints:</strong> if content shows a person's private areas, shows full or partial nudity or sexual acts, or impersonates someone – including artificially morphed images – we will act on it within 24 hours, as required by Rule 3(2)(b).</li>
    </ul>

    <h2>Quick ways to report</h2>
    <p>For most problems, the fastest route is the <strong>Report</strong> button in the app or on the website:</p>
    <ul>
        <li>Use <strong>Report this story</strong> on any news card to flag an error, a misleading summary or sensitive content.</li>
        <li>Use <strong>Report</strong> on any comment or user poll to flag abuse, hate, spam or anything that breaks our <a href="{{ route('community') }}">Community rules</a>.</li>
    </ul>
    <p>If you are not satisfied with the result, or your complaint is formal or legal in nature, write to the Grievance Officer.</p>

    <h2>How to file a complaint</h2>
    <p>Send an email or letter to the Grievance Officer with:</p>
    <ol>
        <li>Your full name.</li>
        <li>Your contact details – email address and/or mobile number.</li>
        <li>The link (URL) of the story, comment or poll you are complaining about.</li>
        <li>A clear description of the problem and what you would like us to do.</li>
        <li>Any supporting documents, such as screenshots, court orders or proof of identity if you are complaining about impersonation.</li>
    </ol>
    <p>If you are complaining on behalf of someone else, please say so and explain your authority to act for them. Incomplete complaints may take longer to resolve, as we may need to ask you for more details.</p>

    <h2>If you are not satisfied</h2>
    <p>Part III of the IT Rules, 2021 sets up a three-level system for complaints about digital news publishers:</p>
    <ol>
        <li><strong>Level 1:</strong> the publisher – that is, our Grievance Officer.</li>
        <li><strong>Level 2:</strong> a self-regulating body of publishers, which can hear appeals if you are not satisfied with our decision or we do not decide within 15 days.</li>
        <li><strong>Level 3:</strong> the oversight mechanism of the Ministry of Information and Broadcasting, Government of India.</li>
    </ol>
    <p>When we decide your complaint, we will tell you how to escalate it to the next level if you disagree.</p>

    <h2>Related pages</h2>
    <ul>
        <li><a href="{{ route('privacy') }}">Privacy policy</a></li>
        <li><a href="{{ route('terms') }}">Terms of use</a></li>
        <li><a href="{{ route('community') }}">Community rules</a></li>
        <li><a href="{{ route('about') }}">About {{ site_name() }}</a></li>
    </ul>
</x-static-page>
